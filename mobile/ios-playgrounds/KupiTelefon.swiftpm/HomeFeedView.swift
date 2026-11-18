import SwiftUI

struct HomeFeedView: View {
    @StateObject private var loader = HomeFeedLoader()
    @State private var selectedAd: AdListing?
    @State private var searchDraft = ""

    private let brandGreen = Color(red: 0.239, green: 0.604, blue: 0.314)
    private let pageBg = Color(red: 0.949, green: 0.949, blue: 0.949)

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                searchBar
                categoryBar

                Group {
                    if loader.isLoading && loader.ads.isEmpty {
                        ProgressView("Ucitavanje oglasa...")
                            .tint(brandGreen)
                            .frame(maxWidth: .infinity, maxHeight: .infinity)
                    } else if let err = loader.errorMessage, loader.ads.isEmpty {
                        VStack(spacing: 12) {
                            Text("Ne mogu da učitam oglase")
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
                            .tint(brandGreen)
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
                                    ProgressView()
                                        .padding(.vertical, 12)
                                }
                            }
                            .padding(.horizontal, 12)
                            .padding(.vertical, 10)
                        }
                        .refreshable {
                            await loader.refresh()
                        }
                    }
                }
            }
            .background(pageBg.ignoresSafeArea())
            .navigationTitle("KupiTelefon")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button {
                        Task { await loader.refresh() }
                    } label: {
                        Image(systemName: "arrow.clockwise")
                    }
                    .accessibilityLabel("Osveži")
                }
            }
            .sheet(item: $selectedAd) { ad in
                SiteBrowserSheet(url: ad.detailURL, title: ad.title)
            }
            .task {
                if loader.ads.isEmpty {
                    await loader.refresh()
                }
            }
        }
    }

    private var searchBar: some View {
        HStack(spacing: 8) {
            Image(systemName: "magnifyingglass")
                .foregroundColor(.secondary)
            TextField("Pretraga oglasa...", text: $searchDraft)
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
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .background(Color.white)
        .overlay(alignment: .bottom) {
            Rectangle().fill(Color(red: 0.961, green: 0.773, blue: 0.094)).frame(height: 2)
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
                            .padding(.horizontal, 12)
                            .padding(.vertical, 8)
                            .background(loader.category == cat ? brandGreen : Color.white)
                            .foregroundColor(loader.category == cat ? Color.white : Color.primary)
                            .cornerRadius(18)
                            .overlay(
                                RoundedRectangle(cornerRadius: 18)
                                    .stroke(
                                        Color(red: 0.86, green: 0.86, blue: 0.86),
                                        lineWidth: loader.category == cat ? 0 : 1
                                    )
                            )
                    }
                    .buttonStyle(.plain)
                }
            }
            .padding(.horizontal, 12)
            .padding(.vertical, 10)
        }
        .background(pageBg)
    }
}

struct SiteBrowserSheet: View {
    let url: URL
    let title: String
    @Environment(\.dismiss) private var dismiss
    @StateObject private var model: WebViewModel

    init(url: URL, title: String) {
        self.url = url
        self.title = title
        _model = StateObject(wrappedValue: WebViewModel(startURL: url))
    }

    var body: some View {
        NavigationStack {
            SiteWebView(model: model)
                .ignoresSafeArea(edges: .bottom)
                .navigationTitle(title)
                .navigationBarTitleDisplayMode(.inline)
                .toolbar {
                    ToolbarItem(placement: .cancellationAction) {
                        Button("Zatvori") { dismiss() }
                    }
                    ToolbarItem(placement: .topBarTrailing) {
                        Button {
                            model.reload()
                        } label: {
                            Image(systemName: "arrow.clockwise")
                        }
                    }
                }
        }
    }
}
