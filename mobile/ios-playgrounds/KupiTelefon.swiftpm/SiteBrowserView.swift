import SwiftUI

struct SiteBrowserView: View {
    let title: String
    let url: URL
    var showsClose: Bool = false
    var embedMode: Bool = false

    @Environment(\.dismiss) private var dismiss
    @StateObject private var model: WebViewModel

    init(title: String, url: URL, showsClose: Bool = false, embedMode: Bool = false) {
        self.title = title
        self.url = url
        self.showsClose = showsClose
        self.embedMode = embedMode
        _model = StateObject(wrappedValue: WebViewModel(startURL: url))
    }

    var body: some View {
        ZStack {
            SiteWebView(model: model)

            if model.isLoading && !model.hasLoadedOnce {
                ProgressView()
                    .tint(Brand.green)
            }

            if model.lastError != nil {
                VStack(spacing: 12) {
                    Text(model.lastError ?? "Greska")
                        .font(.footnote)
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal)
                    Button("Ponovo") { model.reload() }
                        .buttonStyle(.borderedProminent)
                        .tint(Brand.green)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .background(Brand.bg)
            }
        }
        .navigationTitle(embedMode ? "" : title)
        .navigationBarTitleDisplayMode(.inline)
        .navigationBarHidden(embedMode)
        .toolbar {
            if !embedMode {
                if showsClose {
                    ToolbarItem(placement: .cancellationAction) {
                        Button("Zatvori") { dismiss() }
                    }
                }
                ToolbarItem(placement: .topBarTrailing) {
                    Button {
                        model.reload()
                    } label: {
                        Image(systemName: "arrow.clockwise")
                    }
                }
            }
        }
    }
}

struct SiteBrowserSheet: View {
    let link: SiteLink

    var body: some View {
        NavigationStack {
            SiteBrowserView(title: link.title, url: link.url, showsClose: true)
        }
    }
}
