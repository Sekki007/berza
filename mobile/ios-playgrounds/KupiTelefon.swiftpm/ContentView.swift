import SwiftUI

private enum Brand {
    static let green = Color(red: 0.239, green: 0.604, blue: 0.314)
}

struct ContentView: View {
    @State private var tab = 0

    var body: some View {
        TabView(selection: $tab) {
            HomeFeedView()
                .tabItem {
                    Label("Oglasi", systemImage: "house.fill")
                }
                .tag(0)

            WebTabView(
                title: "Pretraga",
                url: URL(string: "https://kupitelefon.rs/#search")!
            )
            .tabItem {
                Label("Pretraga", systemImage: "magnifyingglass")
            }
            .tag(1)

            WebTabView(
                title: "Besplatno",
                url: URL(string: "https://kupitelefon.rs/postavi-oglas")!
            )
            .tabItem {
                Label("Besplatno", systemImage: "plus.circle.fill")
            }
            .tag(2)

            WebTabView(
                title: "Nalog",
                url: URL(string: "https://kupitelefon.rs/nalog.php")!
            )
            .tabItem {
                Label("Nalog", systemImage: "person.fill")
            }
            .tag(3)
        }
        .tint(Brand.green)
        .preferredColorScheme(.light)
    }
}

/// Web ekran za delove koje još nemamo native (forma, nalog, pretraga na sajtu).
struct WebTabView: View {
    let title: String
    let url: URL
    @StateObject private var model: WebViewModel

    init(title: String, url: URL) {
        self.title = title
        self.url = url
        _model = StateObject(wrappedValue: WebViewModel(startURL: url))
    }

    var body: some View {
        NavigationStack {
            ZStack {
                SiteWebView(model: model)
                    .ignoresSafeArea(edges: .bottom)

                if model.isLoading && !model.hasLoadedOnce {
                    ProgressView()
                        .tint(Brand.green)
                }

                if model.lastError != nil {
                    VStack(spacing: 12) {
                        Text(model.lastError ?? "Greška")
                            .font(.footnote)
                            .foregroundStyle(.secondary)
                            .multilineTextAlignment(.center)
                            .padding(.horizontal)
                        Button("Ponovo") { model.reload() }
                            .buttonStyle(.borderedProminent)
                            .tint(Brand.green)
                    }
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
                    .background(Color(red: 0.949, green: 0.949, blue: 0.949))
                }
            }
            .navigationTitle(title)
            .navigationBarTitleDisplayMode(.inline)
        }
    }
}
