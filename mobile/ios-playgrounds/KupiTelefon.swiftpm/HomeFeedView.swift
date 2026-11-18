import SwiftUI

struct HomeFeedView: View {
    @StateObject private var loader = HomeFeedLoader()
    @State private var selectedAd: AdListing?
    @State private var openLink: SiteLink?
    @State private var searchDraft = ""

    var body: some View {
        VStack(spacing: 0) {
            headerBlock
            categoryBar
            feedBody
        }
        .background(Brand.bg.ignoresSafeArea())
        .sheet(item: $selectedAd) { ad in
            NavigationStack {
                SiteBrowserView(title: ad.title, url: ad.detailURL, showsClose: true)
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

    private var headerBlock: some View {
        VStack(spacing: 12) {
            HStack(spacing: 12) {
                BrandLogo(size: 26)
                Spacer(minLength: 8)
                iconButton("bell") {
                    openLink = SiteLink("Obavestenja", path: "/nalog.php?tab=obavestenja")
                }
                iconButton("heart") {
                    openLink = SiteLink("Omiljeni", path: "/favorites.php")
                }
            }

            HStack(spacing: 10) {
                Image(systemName: "magnifyingglass")
                    .foregroundColor(Brand.muted)
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
                            .foregroundColor(Brand.muted)
                    }
                }
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .background(Brand.bg)
            .cornerRadius(14)
        }
        .padding(.horizontal, 16)
        .padding(.top, 10)
        .padding(.bottom, 12)
        .background(Color.white)
        .overlay(alignment: .bottom) {
            Rectangle().fill(Brand.yellow).frame(height: 3)
        }
    }

    private func iconButton(_ systemName: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Image(systemName: systemName)
                .font(.system(size: 18, weight: .semibold))
                .foregroundColor(Brand.text)
                .frame(width: 40, height: 40)
                .background(Brand.bg)
                .clipShape(Circle())
        }
        .buttonStyle(.plain)
    }

    private var categoryBar: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                ForEach(FeedCategory.allCases) { cat in
                    let on = loader.category == cat
                    Button {
                        Task { await loader.apply(category: cat) }
                    } label: {
                        Text(cat.title)
                            .font(.system(size: 13, weight: .bold))
                            .padding(.horizontal, 16)
                            .padding(.vertical, 10)
                            .background(on ? Brand.green : Color.white)
                            .foregroundColor(on ? Color.white : Brand.text)
                            .cornerRadius(22)
                            .overlay(
                                RoundedRectangle(cornerRadius: 22)
                                    .stroke(on ? Brand.green : Brand.border, lineWidth: 1)
                            )
                    }
                    .buttonStyle(.plain)
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
        }
        .background(Brand.bg)
    }

    @ViewBuilder
    private var feedBody: some View {
        if loader.isLoading && loader.ads.isEmpty {
            VStack(spacing: 14) {
                ProgressView().tint(Brand.green).scaleEffect(1.2)
                Text("Ucitavanje oglasa...")
                    .font(.subheadline)
                    .foregroundColor(Brand.muted)
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity)
        } else if let err = loader.errorMessage, loader.ads.isEmpty {
            VStack(spacing: 14) {
                Image(systemName: "wifi.exclamationmark")
                    .font(.system(size: 34))
                    .foregroundColor(Brand.muted)
                Text("Ne mogu da ucitam oglase")
                    .font(.headline)
                Text(err)
                    .font(.footnote)
                    .foregroundColor(.secondary)
                    .multilineTextAlignment(.center)
                    .padding(.horizontal, 24)
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
                LazyVStack(spacing: 12) {
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
                        ProgressView().tint(Brand.green).padding(.vertical, 16)
                    }
                }
                .padding(.horizontal, 14)
                .padding(.top, 4)
                .padding(.bottom, 20)
            }
            .refreshable {
                await loader.refresh()
            }
        }
    }
}
