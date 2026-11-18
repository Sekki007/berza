import SwiftUI

private enum Brand {
    static let green = Color(red: 0.239, green: 0.604, blue: 0.314)
    static let yellow = Color(red: 0.961, green: 0.773, blue: 0.094)
    static let bg = Color(red: 0.949, green: 0.949, blue: 0.949)
}

struct ContentView: View {
    @StateObject private var model = WebViewModel(
        startURL: URL(string: "https://kupitelefon.rs")!
    )

    var body: some View {
        ZStack {
            Brand.bg.ignoresSafeArea()

            SiteWebView(model: model)
                .opacity(model.hasLoadedOnce && model.lastError == nil ? 1 : 0)
                .ignoresSafeArea(edges: .bottom)

            if !model.hasLoadedOnce || model.isLoading {
                loadingChrome
                    .allowsHitTesting(!model.hasLoadedOnce)
            }

            if model.lastError != nil {
                errorChrome
            }
        }
        .preferredColorScheme(.light)
    }

    private var loadingChrome: some View {
        VStack(spacing: 16) {
            if !model.hasLoadedOnce {
                Text(brandTitle)
                    .font(.system(size: 28, weight: .heavy, design: .rounded))
                    .tracking(-0.5)

                Text("Učitavanje…")
                    .font(.subheadline)
                    .foregroundStyle(.secondary)

                ProgressView()
                    .tint(Brand.green)
                    .padding(.top, 4)
            } else {
                VStack(spacing: 0) {
                    ProgressView()
                        .progressViewStyle(.linear)
                        .tint(Brand.green)
                    Spacer()
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .top)
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(model.hasLoadedOnce ? Color.clear : Brand.bg)
    }

    private var brandTitle: AttributedString {
        var kupi = AttributedString("Kupi")
        kupi.foregroundColor = Brand.green
        var telefon = AttributedString("Telefon")
        telefon.foregroundColor = Color(red: 0.2, green: 0.2, blue: 0.2)
        return kupi + telefon
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
        .background(Brand.bg.opacity(0.96))
    }
}
