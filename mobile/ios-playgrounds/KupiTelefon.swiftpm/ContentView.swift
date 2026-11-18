import SwiftUI

struct ContentView: View {
    @StateObject private var model = WebViewModel(
        startURL: URL(string: "https://kupitelefon.rs")!
    )

    var body: some View {
        VStack(spacing: 0) {
            if model.isLoading {
                ProgressView()
                    .progressViewStyle(.linear)
                    .tint(Color.accentColor)
            }

            SiteWebView(model: model)
                .ignoresSafeArea(edges: .bottom)
        }
        .safeAreaInset(edge: .bottom, spacing: 0) {
            if model.lastError != nil {
                HStack(spacing: 12) {
                    Text(model.lastError ?? "Greška pri učitavanju")
                        .font(.footnote)
                        .foregroundStyle(.secondary)
                        .lineLimit(2)
                    Spacer(minLength: 8)
                    Button("Ponovo") {
                        model.reload()
                    }
                    .buttonStyle(.borderedProminent)
                    .controlSize(.small)
                }
                .padding(.horizontal, 14)
                .padding(.vertical, 10)
                .background(.ultraThinMaterial)
            }
        }
    }
}
