import SwiftUI

struct FavoritesView: View {
    @EnvironmentObject private var session: AppSession
    @State private var ads: [AdListing] = []
    @State private var loading = false
    @State private var errorText: String?
    @State private var showLogin = false

    var body: some View {
        Group {
            if !session.isLoggedIn {
                VStack(spacing: 14) {
                    Image(systemName: "heart.fill").font(.largeTitle).foregroundColor(Brand.green)
                    Text("Prijavi se za omiljene")
                    Button("Prijava") { showLogin = true }
                        .buttonStyle(.borderedProminent).tint(Brand.green)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
            } else if loading && ads.isEmpty {
                ProgressView().tint(Brand.green).frame(maxWidth: .infinity, maxHeight: .infinity)
            } else if ads.isEmpty {
                Text(errorText ?? "Nema omiljenih oglasa.")
                    .foregroundColor(Brand.muted)
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
            } else {
                ScrollView {
                    LazyVStack(spacing: 12) {
                        ForEach(ads) { ad in
                            NavigationLink(value: ad) {
                                AdCardView(ad: ad)
                            }
                            .buttonStyle(.plain)
                        }
                    }
                    .padding(14)
                }
            }
        }
        .background(Brand.bg.ignoresSafeArea())
        .navigationTitle("Omiljeni")
        .navigationDestination(for: AdListing.self) { ad in
            AdDetailView(listing: ad)
        }
        .sheet(isPresented: $showLogin) {
            NavigationStack { LoginView().environmentObject(session) }
        }
        .task {
            if session.isLoggedIn { await load() }
        }
        .refreshable { await load() }
    }

    private func load() async {
        loading = true
        defer { loading = false }
        do {
            let html = try await KTNetwork.getHTML("/favorites.php")
            if html.contains("/prijava") && !html.contains("listing-card") {
                session.isLoggedIn = false
                return
            }
            ads = HomeFeedParser.parse(html: html)
            if ads.isEmpty {
                errorText = "Nema omiljenih oglasa."
            }
        } catch {
            errorText = error.localizedDescription
        }
    }
}
