import SwiftUI

struct HomeFeedView: View {
    @StateObject private var loader = HomeFeedLoader()
    @State private var selectedAd: AdListing?
    @State private var openLink: SiteLink?
    @State private var searchDraft = ""

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                header
                searchBar
                categoryBar
                feedBody
            }
            .background(Brand.bg.ignoresSafeArea())
            .toolbar(.hidden, for: .navigationBar)
            .sheet(item: $selectedAd) { ad in
                NavigationStack {
                    SiteBrowserView(title: "Oglas", url: ad.detailURL, showsClose: true)
                }
            }
            .sheet(item: $openLink) { link in
                SiteBrowserSheet(link: link)
            }
            .task {
                if loader.ads.isEmpty {
                    await loader.refresh()
                }
            }
        }
    }

    private var header: some View {
        HStack {
            BrandLogo(size: 24)
            Spacer()
            Button {
                openLink = SiteLink("Obavestenja", path: "/nalog.php?tab=obavestenja")
            } label: {
                Image(systemName: "bell")
                    .font(.system(size: 18, weight: .semibold))
                    .foregroundColor(Brand.text)
                    .frame(width: 36, height: 36)
            }
            Button {
                openLink = SiteLink("Omiljeni", path: "/favorites.php")
            } label: {
                Image(systemName: "heart")
                    .font(.system(size: 18, weight: .semibold))
                    .foregroundColor(Brand.text)
                    .frame(width: 36, height: 36)
            }
        }
        .padding(.horizontal, 16)
        .padding(.top, 8)
        .padding(.bottom, 6)
        .background(Color.white)
    }

    private var searchBar: some View {
        HStack(spacing: 8) {
            Image(systemName: "magnifyingglass")
                .foregroundColor(.secondary)
            TextField("Pretraga oglasa", text: $searchDraft)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
                .submitLabel(.search)
                .onSubmit {
                    loader.query = searchDraft
                    Task { await loader.applySearch() }
                }
            if !searchDraft.isEmpty {
                Button {
                    searchDraft = ""
                    loader.query = ""
                    Task { await loader.applySearch() }
                } label: {
                    Image(systemName: "xmark.circle.fill")
                        .foregroundColor(.secondary)
                }
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 11)
        .background(Color.white)
        .overlay(alignment: .bottom) {
            Rectangle().fill(Brand.yellow).frame(height: 2)
        }
    }

    private var categoryBar: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                ForEach(FeedCategory.allCases) { cat in
                    Button {
                        Task { await loader.apply(category: cat) }
                    } label: {
                        Text(cat.title)
                            .font(.system(size: 13, weight: .semibold))
                            .padding(.horizontal, 14)
                            .padding(.vertical, 8)
                            .background(loader.category == cat ? Brand.green : Color.white)
                            .foregroundColor(loader.category == cat ? Color.white : Brand.text)
                            .cornerRadius(18)
                            .overlay(
                                RoundedRectangle(cornerRadius: 18)
                                    .stroke(Brand.border, lineWidth: loader.category == cat ? 0 : 1)
                            )
                    }
                    .buttonStyle(.plain)
                }
            }
            .padding(.horizontal, 12)
            .padding(.vertical, 10)
        }
        .background(Brand.bg)
    }

    @ViewBuilder
    private var feedBody: some View {
        if loader.isLoading && loader.ads.isEmpty {
            ProgressView("Ucitavanje oglasa...")
                .tint(Brand.green)
                .frame(maxWidth: .infinity, maxHeight: .infinity)
        } else if let err = loader.errorMessage, loader.ads.isEmpty {
            VStack(spacing: 12) {
                Text("Ne mogu da ucitam oglase")
                    .font(.headline)
                Text(err)
                    .font(.footnote)
                    .foregroundColor(.secondary)
                    .multilineTextAlignment(.center)
                    .padding(.horizontal)
                Button("Ponovo") {
                    Task { await loader.refresh() }
                }
                .buttonStyle(.borderedProminent)
                .tint(Brand.green)
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity)
        } else if loader.ads.isEmpty {
            Text("Nema oglasa za ovaj filter.")
                .foregroundColor(.secondary)
                .frame(maxWidth: .infinity, maxHeight: .infinity)
        } else {
            ScrollView {
                LazyVStack(spacing: 10) {
                    ForEach(loader.ads) { ad in
                        Button {
                            selectedAd = ad
                        } label: {
                            AdCardView(ad: ad)
                        }
                        .buttonStyle(.plain)
                        .onAppear {
                            Task { await loader.loadMoreIfNeeded(current: ad) }
                        }
                    }
                    if loader.isLoadingMore {
                        ProgressView().padding(.vertical, 12)
                    }
                }
                .padding(.horizontal, 12)
                .padding(.vertical, 8)
            }
            .refreshable {
                await loader.refresh()
            }
        }
    }
}
