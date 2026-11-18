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
        .onChange(of: session.isLoggedIn) { logged in
            if logged { Task { await load() } }
            else { ads = [] }
        }
    }

    private func load() async {
        loading = true
        defer { loading = false }
        do {
            let json = try await KTNetwork.apiFavorites()
            if let csrf = json["csrf"] as? String, !csrf.isEmpty {
                session.csrf = csrf
            }
            ads = MobileAPIMapper.listings(from: json)
            errorText = ads.isEmpty ? "Nema omiljenih oglasa." : nil
        } catch {
            errorText = error.localizedDescription
        }
    }
}
