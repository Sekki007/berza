import SwiftUI

private enum Brand {
    static let green = Color(red: 0.239, green: 0.604, blue: 0.314)
    static let bg = Color(red: 0.949, green: 0.949, blue: 0.949)
}

struct ContentView: View {
    @StateObject private var model = WebViewModel(
        startURL: URL(string: "https://kupitelefon.rs")!
    )

    var body: some View {
        ZStack {
            Brand.bg.ignoresSafeArea()

            // WebView uvek vidljiv — ne sakrivati opacity-jem (beli ekran).
            SiteWebView(model: model)
                .ignoresSafeArea(edges: .bottom)

            if !model.hasLoadedOnce && model.lastError == nil {
                splash
            }

            if model.hasLoadedOnce && model.isLoading {
                VStack {
                    ProgressView()
                        .progressViewStyle(.linear)
                        .tint(Brand.green)
                    Spacer()
                }
                .allowsHitTesting(false)
            }

            if model.lastError != nil {
                errorChrome
            }
        }
        .preferredColorScheme(.light)
        .onAppear {
            model.startLoadWatchdog()
        }
    }

    private var splash: some View {
        VStack(spacing: 14) {
            HStack(spacing: 0) {
                Text("Kupi")
                    .foregroundColor(Brand.green)
                Text("Telefon")
                    .foregroundColor(Color(red: 0.2, green: 0.2, blue: 0.2))
            }
            .font(.system(size: 28, weight: .heavy, design: .rounded))

            Text("Učitavanje…")
                .font(.subheadline)
                .foregroundStyle(.secondary)

            ProgressView()
                .tint(Brand.green)
                .padding(.top, 2)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Brand.bg)
    }

    private var errorChrome: some View {
        VStack(spacing: 14) {
            Text("Nema veze")
                .font(.headline)
            Text(model.lastError ?? "Proveri internet i pokušaj ponovo.")
                .font(.footnote)
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 28)
            Button("Ponovo") {
                model.reload()
            }
            .buttonStyle(.borderedProminent)
            .tint(Brand.green)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Brand.bg)
    }
}
