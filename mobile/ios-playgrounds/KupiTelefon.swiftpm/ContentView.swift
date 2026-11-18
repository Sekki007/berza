import SwiftUI

struct ContentView: View {
    @EnvironmentObject private var session: AppSession
    @State private var tab = 0

    var body: some View {
        VStack(spacing: 0) {
            Group {
                switch tab {
                case 0: HomeFeedView()
                case 1: SearchHubView()
                case 2: PostAdNativeView()
                case 3: MessagesInboxView()
                default: AccountHubView()
                }
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity)

            MainTabBar(tab: $tab, messagesBadge: session.unreadMessages)
        }
        .background(Brand.bg.ignoresSafeArea())
        .preferredColorScheme(.light)
        .task {
            await session.bootstrap()
        }
    }
}
