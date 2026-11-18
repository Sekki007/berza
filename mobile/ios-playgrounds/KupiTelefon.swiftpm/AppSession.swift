import SwiftUI

final class AppSession: ObservableObject {
    @Published var isLoggedIn = false
    @Published var username: String = ""
    @Published var displayName: String = ""
    @Published var csrf: String = ""
    @Published var unreadMessages = 0
    @Published var unreadNotifications = 0
    @Published var isBusy = false
    @Published var lastError: String?

    func bootstrap() async {
        await refreshSession()
    }

    func refreshSession() async {
        do {
            let html = try await KTNetwork.getHTML("/nalog.php")
            if let token = KTNetwork.extractCSRF(from: html) {
                csrf = token
            }
            let logged = html.contains("data-logged-in=\"1\"")
                || html.contains("tab=oglasi")
                || html.contains("Moji oglasi")
                || html.contains("/logout.php")
            isLoggedIn = logged
            if logged {
                if let name = HomeFeedParser.firstMatch(in: html, pattern: #"kp-account-name[^>]*>([\s\S]*?)<"#)
                    ?? HomeFeedParser.firstMatch(in: html, pattern: #"class="[^"]*user-name[^"]*"[^>]*>([\s\S]*?)<"#) {
                    displayName = HomeFeedParser.decodeHTML(name)
                }
                await refreshBadges()
            } else {
                unreadMessages = 0
                unreadNotifications = 0
            }
        } catch {}
    }

    func login(username: String, password: String) async throws {
        isBusy = true
        lastError = nil
        defer { isBusy = false }

        let loginPage = try await KTNetwork.getHTML("/prijava")
        let token = KTNetwork.extractCSRF(from: loginPage) ?? csrf
        let result = try await KTNetwork.postForm(
            path: "/prijava",
            fields: [
                "username": username,
                "password": password,
                "remember_me": "1",
                "next": "/nalog.php"
            ],
            csrf: token
        )

        if result.html.contains("verify-phone")
            || (result.finalURL?.absoluteString.contains("verify-phone") ?? false) {
            throw KTAuthError.phoneVerifyRequired
        }

        await refreshSession()
        if !isLoggedIn {
            throw KTAuthError.invalidCredentials
        }
        self.username = username
    }

    func logout() async {
        _ = try? await KTNetwork.getHTML("/logout.php")
        isLoggedIn = false
        username = ""
        displayName = ""
        unreadMessages = 0
        unreadNotifications = 0
        csrf = ""
        await refreshSession()
    }

    func refreshBadges() async {
        guard isLoggedIn else { return }
        do {
            let json = try await KTNetwork.getJSON("/api/messages.php?action=unread") as? [String: Any]
            if let ok = json?["ok"] as? Bool, ok {
                unreadMessages = json?["unread"] as? Int ?? 0
                unreadNotifications = json?["unread_notifications"] as? Int ?? 0
            }
        } catch {}
    }

    func ensureCSRF() async -> String {
        if !csrf.isEmpty { return csrf }
        let html = try? await KTNetwork.getHTML(isLoggedIn ? "/nalog.php" : "/prijava")
        if let html = html, let token = KTNetwork.extractCSRF(from: html) {
            csrf = token
        }
        return csrf
    }
}
