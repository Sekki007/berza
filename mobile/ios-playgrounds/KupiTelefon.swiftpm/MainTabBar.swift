import SwiftUI

struct MainTabBar: View {
    @Binding var tab: Int
    var messagesBadge: Int = 0

    private let items: [(title: String, icon: String)] = [
        ("Oglasi", "house.fill"),
        ("Pretraga", "magnifyingglass"),
        ("Besplatno", "plus"),
        ("Poruke", "bubble.left.and.bubble.right.fill"),
        ("Nalog", "person.fill")
    ]

    var body: some View {
        HStack(alignment: .bottom, spacing: 0) {
            tabButton(0)
            tabButton(1)
            centerButton
            tabButton(3)
            tabButton(4)
        }
        .padding(.horizontal, 10)
        .padding(.top, 8)
        .padding(.bottom, 6)
        .background(
            Color.white
                .overlay(alignment: .top) {
                    Rectangle()
                        .fill(Brand.yellow)
                        .frame(height: 2)
                }
                .shadow(color: Color.black.opacity(0.06), radius: 8, y: -2)
        )
    }

    private func tabButton(_ index: Int) -> some View {
        let active = tab == index
        return Button {
            tab = index
        } label: {
            VStack(spacing: 4) {
                ZStack(alignment: .topTrailing) {
                    Image(systemName: items[index].icon)
                        .font(.system(size: 20, weight: active ? .semibold : .regular))
                        .frame(height: 24)

                    if index == 3 && messagesBadge > 0 {
                        Text(messagesBadge > 9 ? "9+" : "\(messagesBadge)")
                            .font(.system(size: 10, weight: .bold))
                            .foregroundColor(.white)
                            .padding(.horizontal, 4)
                            .padding(.vertical, 1)
                            .background(Color.red)
                            .clipShape(Capsule())
                            .offset(x: 10, y: -6)
                    }
                }
                Text(items[index].title)
                    .font(.system(size: 10, weight: active ? .bold : .medium))
            }
            .foregroundColor(active ? Brand.green : Brand.muted)
            .frame(maxWidth: .infinity)
        }
        .buttonStyle(.plain)
    }

    private var centerButton: some View {
        Button {
            tab = 2
        } label: {
            VStack(spacing: 4) {
                ZStack {
                    Circle()
                        .fill(Brand.green)
                        .frame(width: 56, height: 56)
                        .shadow(color: Brand.green.opacity(0.35), radius: 8, y: 3)
                    Image(systemName: "plus")
                        .font(.system(size: 26, weight: .bold))
                        .foregroundColor(.white)
                }
                .offset(y: -14)

                Text("Besplatno")
                    .font(.system(size: 10, weight: tab == 2 ? .bold : .medium))
                    .foregroundColor(tab == 2 ? Brand.green : Brand.muted)
                    .offset(y: -10)
            }
            .frame(maxWidth: .infinity)
        }
        .buttonStyle(.plain)
    }
}
