import SwiftUI

struct CatalogBrowseView: View {
    @StateObject private var loader = HomeFeedLoader()

    var body: some View {
        NativeFeedHost(title: "Katalog", loader: loader)
            .task {
                await loader.apply(category: .telefon)
            }
    }
}

struct ServicesBrowseView: View {
    @StateObject private var loader = HomeFeedLoader()

    var body: some View {
        NativeFeedHost(title: "Servisi", loader: loader)
            .task {
                await loader.apply(category: .servis)
            }
    }
}

private struct NativeFeedHost: View {
    let title: String
    @ObservedObject var loader: HomeFeedLoader

    var body: some View {
        Group {
            if loader.isLoading && loader.ads.isEmpty {
                ProgressView().tint(Brand.green)
            } else if loader.ads.isEmpty {
                Text(loader.errorMessage ?? "Nema rezultata.")
                    .foregroundColor(Brand.muted)
            } else {
                ScrollView {
                    LazyVStack(spacing: 12) {
                        ForEach(loader.ads) { ad in
                            NavigationLink(value: ad) {
                                AdCardView(ad: ad)
                            }
                            .buttonStyle(.plain)
                            .onAppear { Task { await loader.loadMoreIfNeeded(current: ad) } }
                        }
                    }
                    .padding(14)
                }
                .refreshable { await loader.refresh() }
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Brand.bg.ignoresSafeArea())
        .navigationTitle(title)
        .navigationDestination(for: AdListing.self) { ad in
            AdDetailView(listing: ad)
        }
    }
}

struct MyAdsView: View {
    @EnvironmentObject private var session: AppSession
    @State private var ads: [AdListing] = []
    @State private var filter = "all"
    @State private var loading = false
    @State private var errorText: String?
    @State private var showLogin = false
    @State private var toast = ""

    private let filters: [(String, String)] = [
        ("all", "Svi"),
        ("active", "Aktivni"),
        ("sold", "Prodati"),
        ("inactive", "Neaktivni")
    ]

    var body: some View {
        Group {
            if !session.isLoggedIn {
                VStack(spacing: 12) {
                    Text("Prijavi se za svoje oglase")
                    Button("Prijava") { showLogin = true }
                        .buttonStyle(.borderedProminent).tint(Brand.green)
                }
            } else if loading && ads.isEmpty {
                ProgressView().tint(Brand.green)
            } else {
                VStack(spacing: 0) {
                    ScrollView(.horizontal, showsIndicators: false) {
                        HStack(spacing: 8) {
                            ForEach(filters, id: \.0) { item in
                                Button {
                                    filter = item.0
                                    Task { await load() }
                                } label: {
                                    Text(item.1)
                                        .font(.system(size: 13, weight: .bold))
                                        .padding(.horizontal, 14)
                                        .padding(.vertical, 9)
                                        .background(filter == item.0 ? Brand.green : Color.white)
                                        .foregroundColor(filter == item.0 ? .white : Brand.text)
                                        .cornerRadius(18)
                                }
                                .buttonStyle(.plain)
                            }
                        }
                        .padding(12)
                    }

                    if ads.isEmpty {
                        Text(errorText ?? "Nemas oglasa u ovoj kategoriji.")
                            .foregroundColor(Brand.muted)
                            .frame(maxWidth: .infinity, maxHeight: .infinity)
                    } else {
                        ScrollView {
                            LazyVStack(spacing: 12) {
                                ForEach(ads) { ad in
                                    VStack(alignment: .leading, spacing: 8) {
                                        NavigationLink(value: ad) {
                                            AdCardView(ad: ad)
                                        }
                                        .buttonStyle(.plain)

                                        HStack {
                                            if !ad.statusLabel.isEmpty {
                                                Text(ad.statusLabel)
                                                    .font(.caption.weight(.bold))
                                                    .foregroundColor(ad.isSold ? Brand.price : Brand.green)
                                            }
                                            Spacer()
                                            Button(ad.isSold ? "Vrati u prodaju" : "Oznaci prodato") {
                                                Task { await toggleSold(ad) }
                                            }
                                            .font(.caption.weight(.bold))
                                            .foregroundColor(Brand.green)
                                        }
                                        .padding(.horizontal, 4)
                                    }
                                }
                            }
                            .padding(14)
                        }
                    }

                    if !toast.isEmpty {
                        Text(toast)
                            .font(.footnote)
                            .foregroundColor(Brand.green)
                            .padding(.bottom, 8)
                    }
                }
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Brand.bg.ignoresSafeArea())
        .navigationTitle("Moji oglasi")
        .navigationDestination(for: AdListing.self) { ad in
            AdDetailView(listing: ad)
        }
        .sheet(isPresented: $showLogin) { NavigationStack { LoginView().environmentObject(session) } }
        .task { if session.isLoggedIn { await load() } }
        .refreshable { await load() }
        .onChange(of: session.isLoggedIn) { logged in
            if logged { Task { await load() } } else { ads = [] }
        }
    }

    private func load() async {
        loading = true
        defer { loading = false }
        do {
            let json = try await KTNetwork.apiMyAds(filter: filter)
            if let csrf = json["csrf"] as? String, !csrf.isEmpty {
                session.csrf = csrf
            }
            if MobileAPIMapper.stringValue(json["error"]) == "auth_required" {
                session.isLoggedIn = false
                return
            }
            ads = MobileAPIMapper.listings(from: json)
            errorText = ads.isEmpty ? "Nemas oglasa u ovoj kategoriji." : nil
        } catch {
            errorText = error.localizedDescription
            ads = []
        }
    }

    private func toggleSold(_ ad: AdListing) async {
        let csrf = await session.ensureCSRF()
        do {
            let result = try await KTNetwork.apiToggleSold(adId: ad.id, csrf: csrf)
            if let next = result["csrf"] as? String, !next.isEmpty {
                session.csrf = next
            }
            guard MobileAPIMapper.boolValue(result["ok"]) else {
                toast = "Nije uspelo."
                return
            }
            toast = MobileAPIMapper.boolValue(result["sold"]) ? "Oznaceno kao prodato." : "Vraceno u prodaju."
            await load()
        } catch {
            toast = error.localizedDescription
        }
    }
}

struct NotificationsView: View {
    @EnvironmentObject private var session: AppSession
    @State private var items: [AppNotification] = []
    @State private var loading = false
    @State private var showLogin = false

    var body: some View {
        Group {
            if !session.isLoggedIn {
                VStack(spacing: 12) {
                    Text("Prijavi se za obavestenja")
                    Button("Prijava") { showLogin = true }
                        .buttonStyle(.borderedProminent).tint(Brand.green)
                }
            } else if loading && items.isEmpty {
                ProgressView().tint(Brand.green)
            } else if items.isEmpty {
                Text("Nema obavestenja.")
                    .foregroundColor(Brand.muted)
            } else {
                List {
                    ForEach(items) { n in
                        VStack(alignment: .leading, spacing: 4) {
                            HStack {
                                Text(n.title)
                                    .font(.system(size: 15, weight: n.isRead ? .regular : .bold))
                                Spacer()
                                if !n.isRead {
                                    Circle().fill(Brand.green).frame(width: 8, height: 8)
                                }
                            }
                            if !n.body.isEmpty {
                                Text(n.body)
                                    .font(.footnote)
                                    .foregroundColor(Brand.muted)
                            }
                            Text(n.relative)
                                .font(.caption2)
                                .foregroundColor(Brand.muted)
                        }
                        .padding(.vertical, 4)
                    }
                }
                .listStyle(.plain)
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Brand.bg.ignoresSafeArea())
        .navigationTitle("Obavestenja")
        .toolbar {
            if session.isLoggedIn && !items.isEmpty {
                ToolbarItem(placement: .topBarTrailing) {
                    Button("Procitaj sve") {
                        Task { await markAll() }
                    }
                }
            }
        }
        .sheet(isPresented: $showLogin) { NavigationStack { LoginView().environmentObject(session) } }
        .task { if session.isLoggedIn { await load() } }
        .refreshable { await load() }
    }

    private func load() async {
        loading = true
        defer { loading = false }
        do {
            let json = try await KTNetwork.apiNotifications()
            if let csrf = json["csrf"] as? String, !csrf.isEmpty {
                session.csrf = csrf
            }
            let raw = json["items"] as? [[String: Any]] ?? []
            items = raw.map {
                AppNotification(
                    id: MobileAPIMapper.intValue($0["id"]),
                    title: MobileAPIMapper.stringValue($0["title"], fallback: "Obavestenje"),
                    body: MobileAPIMapper.stringValue($0["body"]),
                    relative: MobileAPIMapper.stringValue($0["relative"]),
                    isRead: MobileAPIMapper.boolValue($0["is_read"])
                )
            }
            session.unreadNotifications = MobileAPIMapper.intValue(json["unread"])
        } catch {
            items = []
        }
    }

    private func markAll() async {
        let csrf = await session.ensureCSRF()
        _ = try? await KTNetwork.apiMarkNotificationsRead(csrf: csrf)
        await load()
        await session.refreshBadges()
    }
}

struct AppNotification: Identifiable {
    let id: Int
    let title: String
    let body: String
    let relative: String
    let isRead: Bool
}

struct StaticInfoView: View {
    let title: String
    let path: String
    @State private var text = "Ucitavanje..."

    var body: some View {
        ScrollView {
            Text(text)
                .font(.system(size: 15))
                .foregroundColor(Brand.text)
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(16)
        }
        .background(Brand.bg.ignoresSafeArea())
        .navigationTitle(title)
        .task {
            do {
                let html = try await KTNetwork.getHTML(path)
                let stripped = HomeFeedParser.decodeHTML(
                    HomeFeedParser.firstMatch(in: html, pattern: #"<main[\s\S]*?>([\s\S]*?)</main>"#)
                        ?? HomeFeedParser.firstMatch(in: html, pattern: #"class="content"[^>]*>([\s\S]*?)</div>"#)
                        ?? html
                )
                text = stripped.isEmpty ? "Sadrzaj nije dostupan offline. Otvori kupitelefon.rs\(path)" : String(stripped.prefix(4000))
            } catch {
                text = "Ne mogu da ucitam stranicu. Probaj na kupitelefon.rs\(path)"
            }
        }
    }
}
