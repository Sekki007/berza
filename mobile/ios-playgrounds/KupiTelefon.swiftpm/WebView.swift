import SwiftUI
import WebKit

final class WebViewModel: ObservableObject {
    let startURL: URL

    @Published var isLoading = true
    @Published var hasLoadedOnce = false
    @Published var lastError: String?
    @Published var canGoBack = false
    @Published var canGoForward = false

    weak var webView: WKWebView?
    private var watchdog: Timer?

    init(startURL: URL) {
        self.startURL = startURL
    }

    func startLoadWatchdog() {
        watchdog?.invalidate()
        watchdog = Timer.scheduledTimer(withTimeInterval: 25, repeats: false) { [weak self] _ in
            DispatchQueue.main.async {
                guard let self = self else { return }
                if !self.hasLoadedOnce && self.lastError == nil {
                    self.isLoading = false
                    self.lastError = "Stranica se nije učitala. Proveri Network / Outgoing Connections u Playgrounds."
                }
            }
        }
    }

    func reload() {
        lastError = nil
        isLoading = true
        startLoadWatchdog()
        if let webView = webView, webView.url != nil {
            webView.reload()
        } else {
            webView?.load(URLRequest(url: startURL))
        }
    }

    fileprivate func markLoaded(webView: WKWebView) {
        watchdog?.invalidate()
        watchdog = nil
        isLoading = false
        hasLoadedOnce = true
        lastError = nil
        canGoBack = webView.canGoBack
        canGoForward = webView.canGoForward
    }
}

struct SiteWebView: UIViewRepresentable {
    @ObservedObject var model: WebViewModel

    func makeCoordinator() -> Coordinator {
        Coordinator(model: model)
    }

    func makeUIView(context: Context) -> WKWebView {
        let userContent = WKUserContentController()
        let bootScript = WKUserScript(
            source: """
            window.KtNativeApp = true;
            window.KtNativePlatform = 'ios';
            try {
              document.documentElement.classList.add('kt-native-app');
              var apply = function () {
                if (document.body) document.body.classList.add('kt-native-app');
              };
              apply();
              document.addEventListener('DOMContentLoaded', apply);
            } catch (e) {}
            """,
            injectionTime: .atDocumentStart,
            forMainFrameOnly: true
        )
        userContent.addUserScript(bootScript)

        let config = WKWebViewConfiguration()
        config.allowsInlineMediaPlayback = true
        config.userContentController = userContent
        if #available(iOS 14.0, *) {
            config.defaultWebpagePreferences.allowsContentJavaScript = true
        }

        let webView = WKWebView(frame: .zero, configuration: config)
        webView.navigationDelegate = context.coordinator
        webView.uiDelegate = context.coordinator
        webView.allowsBackForwardNavigationGestures = true
        webView.allowsLinkPreview = false
        webView.scrollView.contentInsetAdjustmentBehavior = .automatic
        webView.backgroundColor = UIColor(red: 0.949, green: 0.949, blue: 0.949, alpha: 1)
        webView.isOpaque = true
        if #available(iOS 15.0, *) {
            webView.underPageBackgroundColor = UIColor(red: 0.949, green: 0.949, blue: 0.949, alpha: 1)
        }

        let baseUA = webView.value(forKey: "userAgent") as? String ?? ""
        webView.customUserAgent = baseUA + " KupiTelefonApp/1.0 (ios)"

        model.webView = webView
        model.startLoadWatchdog()
        var request = URLRequest(url: model.startURL)
        request.timeoutInterval = 30
        webView.load(request)
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
            }
        }

        func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
            webView.evaluateJavaScript(
                """
                window.KtNativeApp = true;
                document.documentElement.classList.add('kt-native-app');
                if (document.body) document.body.classList.add('kt-native-app');
                """
            , completionHandler: nil)

            DispatchQueue.main.async {
                self.model.markLoaded(webView: webView)
            }
        }

        func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
            DispatchQueue.main.async {
                self.model.isLoading = false
                self.model.lastError = error.localizedDescription
            }
        }

        func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
            let ns = error as NSError
            if ns.domain == NSURLErrorDomain && ns.code == NSURLErrorCancelled {
                return
            }
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
