import SwiftUI

struct ContentView: View {
    @State private var tab = 0

    var body: some View {
        VStack(spacing: 0) {
            Group {
                switch tab {
                case 0: HomeFeedView()
                case 1: SearchHubView()
                case 2: PostAdHubView()
                case 3: MessagesHubView()
                default: AccountHubView()
                }
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity)

            MainTabBar(tab: $tab, messagesBadge: 0)
        }
        .background(Brand.bg.ignoresSafeArea())
        .preferredColorScheme(.light)
    }
}
