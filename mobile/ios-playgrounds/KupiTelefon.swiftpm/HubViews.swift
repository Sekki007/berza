import SwiftUI

struct SearchHubView: View {
    @StateObject private var loader = HomeFeedLoader()
    @State private var searchDraft = ""

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                HStack(spacing: 10) {
                    Image(systemName: "magnifyingglass").foregroundColor(Brand.muted)
                    TextField("Pretraga oglasa", text: $searchDraft)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                        .submitLabel(.search)
                        .onSubmit {
                            loader.query = searchDraft
                            Task { await loader.applySearch() }
                        }
                    Button("Trazi") {
                        loader.query = searchDraft
                        Task { await loader.applySearch() }
                    }
                    .font(.system(size: 14, weight: .bold))
                    .foregroundColor(Brand.green)
                }
                .padding(14)
                .background(Color.white)
                .overlay(alignment: .bottom) { Rectangle().fill(Brand.yellow).frame(height: 2) }

                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: 8) {
                        ForEach(FeedCategory.allCases) { cat in
                            Button {
                                Task { await loader.apply(category: cat) }
                            } label: {
                                Text(cat.title)
                                    .font(.system(size: 13, weight: .bold))
                                    .padding(.horizontal, 14)
                                    .padding(.vertical, 9)
                                    .background(loader.category == cat ? Brand.green : Color.white)
                                    .foregroundColor(loader.category == cat ? .white : Brand.text)
                                    .cornerRadius(18)
                            }
                            .buttonStyle(.plain)
                        }
                    }
                    .padding(12)
                }

                if loader.isLoading && loader.ads.isEmpty {
                    ProgressView().tint(Brand.green).frame(maxWidth: .infinity, maxHeight: .infinity)
                } else {
                    ScrollView {
                        LazyVStack(spacing: 12) {
                            ForEach(loader.ads) { ad in
                                NavigationLink(value: ad) { AdCardView(ad: ad) }
                                    .buttonStyle(.plain)
                                    .onAppear { Task { await loader.loadMoreIfNeeded(current: ad) } }
                            }
                        }
                        .padding(14)
                    }
                    .refreshable { await loader.refresh() }
                }
            }
            .background(Brand.bg.ignoresSafeArea())
            .navigationTitle("Pretraga")
            .navigationDestination(for: AdListing.self) { ad in
                AdDetailView(listing: ad)
            }
            .task {
                if loader.ads.isEmpty { await loader.refresh() }
            }
        }
    }
}

struct AccountHubView: View {
    @EnvironmentObject private var session: AppSession
    @State private var showLogin = false

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 16) {
                    profileHeader

                    gridLink("Moji oglasi", icon: "tag.fill", dest: .myAds)
                    gridLink("Omiljeni", icon: "heart.fill", dest: .favorites)
                    gridLink("Obavestenja", icon: "bell.fill", dest: .notifications)
                    gridLink("Katalog", icon: "book.fill", dest: .catalog)
                    gridLink("Servisi", icon: "building.2.fill", dest: .services)
                    gridLink("IMEI provera", icon: "checkmark.shield.fill", dest: .imei)
                    gridLink("Kako radi", icon: "questionmark.circle.fill", dest: .howto)
                    gridLink("Uslovi", icon: "doc.text.fill", dest: .terms)
                    gridLink("Privatnost", icon: "hand.raised.fill", dest: .privacy)

                    if session.isLoggedIn {
                        Button {
                            Task { await session.logout() }
                        } label: {
                            Text("Odjavi se")
                                .font(.system(size: 15, weight: .bold))
                                .frame(maxWidth: .infinity)
                                .padding(.vertical, 14)
                                .background(Color.white)
                                .foregroundColor(Brand.price)
                                .cornerRadius(14)
                        }
                    } else {
                        Button { showLogin = true } label: {
                            Text("Prijavi se")
                                .font(.system(size: 16, weight: .bold))
                                .frame(maxWidth: .infinity)
                                .padding(.vertical, 14)
                                .background(Brand.green)
                                .foregroundColor(.white)
                                .cornerRadius(14)
                        }
                    }
                }
                .padding(16)
            }
            .background(Brand.bg.ignoresSafeArea())
            .navigationTitle("Nalog")
            .navigationDestination(for: AccountDest.self) { dest in
                switch dest {
                case .myAds: MyAdsView()
                case .favorites: FavoritesView()
                case .notifications: NativeListingsPage(title: "Obavestenja", path: "/nalog.php?tab=obavestenja")
                case .catalog: CatalogBrowseView()
                case .services: ServicesBrowseView()
                case .imei: StaticInfoView(title: "Provera IMEI", path: "/provera-imei")
                case .howto: StaticInfoView(title: "Kako radi", path: "/kako-radi")
                case .terms: StaticInfoView(title: "Uslovi", path: "/uslovi")
                case .privacy: StaticInfoView(title: "Privatnost", path: "/privatnost")
                }
            }
            .sheet(isPresented: $showLogin) {
                NavigationStack { LoginView().environmentObject(session) }
            }
            .task { await session.refreshSession() }
        }
    }

    private var profileHeader: some View {
        VStack(spacing: 10) {
            ZStack {
                Circle().fill(Brand.green).frame(width: 76, height: 76)
                Image(systemName: "person.fill").font(.system(size: 30)).foregroundColor(.white)
            }
            BrandLogo(size: 20)
            if session.isLoggedIn {
                Text(session.displayName.isEmpty ? (session.username.isEmpty ? "Prijavljen" : session.username) : session.displayName)
                    .font(.system(size: 15, weight: .semibold))
            } else {
                Text("Gost — prijavi se za punu app")
                    .font(.subheadline)
                    .foregroundColor(Brand.muted)
            }
        }
        .frame(maxWidth: .infinity)
        .padding(.vertical, 22)
        .background(Color.white)
        .cornerRadius(20)
    }

    private func gridLink(_ title: String, icon: String, dest: AccountDest) -> some View {
        NavigationLink(value: dest) {
            HStack(spacing: 12) {
                Image(systemName: icon).foregroundColor(Brand.green).frame(width: 24)
                Text(title)
                    .font(.system(size: 15, weight: .semibold))
                    .foregroundColor(Brand.text)
                Spacer()
                Image(systemName: "chevron.right").foregroundColor(Brand.muted)
            }
            .padding(14)
            .background(Color.white)
            .cornerRadius(14)
        }
    }
}

enum AccountDest: String, Hashable {
    case myAds, favorites, notifications, catalog, services, imei, howto, terms, privacy
}
