import SwiftUI
import WebKit

final class WebViewModel: ObservableObject {
    let startURL: URL

    @Published var isLoading = false
    @Published var lastError: String?
    @Published var canGoBack = false
    @Published var canGoForward = false

    weak var webView: WKWebView?

    init(startURL: URL) {
        self.startURL = startURL
    }

    func reload() {
        lastError = nil
        if let webView = webView, webView.url != nil {
            webView.reload()
        } else {
            webView?.load(URLRequest(url: startURL))
        }
    }

    func goBack() {
        webView?.goBack()
    }

    func goForward() {
        webView?.goForward()
    }
}

struct SiteWebView: UIViewRepresentable {
    @ObservedObject var model: WebViewModel

    func makeCoordinator() -> Coordinator {
        Coordinator(model: model)
    }

    func makeUIView(context: Context) -> WKWebView {
        let config = WKWebViewConfiguration()
        config.allowsInlineMediaPlayback = true
        if #available(iOS 14.0, *) {
            config.defaultWebpagePreferences.allowsContentJavaScript = true
        }

        let webView = WKWebView(frame: .zero, configuration: config)
        webView.navigationDelegate = context.coordinator
        webView.uiDelegate = context.coordinator
        webView.allowsBackForwardNavigationGestures = true
        webView.scrollView.contentInsetAdjustmentBehavior = .automatic
        webView.backgroundColor = .systemBackground
        webView.isOpaque = true

        model.webView = webView
        webView.load(URLRequest(url: model.startURL))
        return webView
    }

    func updateUIView(_ uiView: WKWebView, context: Context) {
        model.webView = uiView
    }

    final class Coordinator: NSObject, WKNavigationDelegate, WKUIDelegate {
        private let model: WebViewModel
        private let allowedHosts: Set<String> = [
            "kupitelefon.rs",
            "www.kupitelefon.rs"
        ]

        init(model: WebViewModel) {
            self.model = model
        }

        private func isAllowedHost(_ host: String?) -> Bool {
            guard let host = host?.lowercased() else { return false }
            if allowedHosts.contains(host) { return true }
            return host.hasSuffix(".kupitelefon.rs")
        }

        private func openExternally(_ url: URL) {
            DispatchQueue.main.async {
                UIApplication.shared.open(url, options: [:], completionHandler: nil)
            }
        }

        func webView(_ webView: WKWebView, didStartProvisionalNavigation navigation: WKNavigation!) {
            DispatchQueue.main.async {
                self.model.isLoading = true
                self.model.lastError = nil
            }
        }

        func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
            DispatchQueue.main.async {
                self.model.isLoading = false
                self.model.canGoBack = webView.canGoBack
                self.model.canGoForward = webView.canGoForward
            }
        }

        func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
            DispatchQueue.main.async {
                self.model.isLoading = false
                self.model.lastError = error.localizedDescription
            }
        }

        func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
            DispatchQueue.main.async {
                self.model.isLoading = false
                self.model.lastError = error.localizedDescription
            }
        }

        func webView(
            _ webView: WKWebView,
            decidePolicyFor navigationAction: WKNavigationAction,
            decisionHandler: @escaping (WKNavigationActionPolicy) -> Void
        ) {
            guard let url = navigationAction.request.url else {
                decisionHandler(.allow)
                return
            }

            let scheme = (url.scheme ?? "").lowercased()

            if scheme == "tel" || scheme == "mailto" || scheme == "sms" || scheme == "whatsapp" {
                openExternally(url)
                decisionHandler(.cancel)
                return
            }

            if scheme == "http" || scheme == "https" {
                if isAllowedHost(url.host) {
                    decisionHandler(.allow)
                } else {
                    openExternally(url)
                    decisionHandler(.cancel)
                }
                return
            }

            if !scheme.isEmpty && scheme != "about" && scheme != "blob" {
                openExternally(url)
                decisionHandler(.cancel)
                return
            }

            decisionHandler(.allow)
        }

        func webView(
            _ webView: WKWebView,
            createWebViewWith configuration: WKWebViewConfiguration,
            for navigationAction: WKNavigationAction,
            windowFeatures: WKWindowFeatures
        ) -> WKWebView? {
            if let url = navigationAction.request.url {
                if isAllowedHost(url.host) {
                    webView.load(URLRequest(url: url))
                } else if let scheme = url.scheme?.lowercased(),
                          scheme == "http" || scheme == "https" || scheme == "tel" || scheme == "mailto" {
                    openExternally(url)
                }
            }
            return nil
        }
    }
}
