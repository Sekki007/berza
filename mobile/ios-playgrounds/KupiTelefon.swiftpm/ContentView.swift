import SwiftUI

struct ContentView: View {
    @State private var tab = 0

    var body: some View {
        TabView(selection: $tab) {
            HomeFeedView()
                .tabItem {
                    Label("Oglasi", systemImage: "house.fill")
                }
                .tag(0)

            SearchHubView()
                .tabItem {
                    Label("Pretraga", systemImage: "magnifyingglass")
                }
                .tag(1)

            PostAdHubView()
                .tabItem {
                    Label("Besplatno", systemImage: "plus.circle.fill")
                }
                .tag(2)

            MessagesHubView()
                .tabItem {
                    Label("Poruke", systemImage: "bubble.left.and.bubble.right.fill")
                }
                .tag(3)

            AccountHubView()
                .tabItem {
                    Label("Nalog", systemImage: "person.fill")
                }
                .tag(4)
        }
        .tint(Brand.green)
        .preferredColorScheme(.light)
    }
}
