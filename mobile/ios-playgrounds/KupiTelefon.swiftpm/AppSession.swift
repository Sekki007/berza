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
            let json = try await KTNetwork.apiAuthStatus()
            if let token = json["csrf"] as? String, !token.isEmpty {
                csrf = token
            }
            isLoggedIn = MobileAPIMapper.boolValue(json["logged_in"])
            if let user = json["user"] as? [String: Any] {
                username = MobileAPIMapper.stringValue(user["username"])
                displayName = MobileAPIMapper.stringValue(user["name"], fallback: username)
            } else if !isLoggedIn {
                username = ""
                displayName = ""
            }
            if isLoggedIn {
                await refreshBadges()
            } else {
                unreadMessages = 0
                unreadNotifications = 0
            }
        } catch {
            // Keep previous state on transient network errors.
        }
    }

    func login(username: String, password: String) async throws {
        isBusy = true
        lastError = nil
        defer { isBusy = false }

        var token = csrf
        if token.isEmpty {
            let boot = try await KTNetwork.apiAuthStatus()
            token = boot["csrf"] as? String ?? ""
            csrf = token
        }

        let result = try await KTNetwork.apiLogin(username: username, password: password, csrf: token)
        if let nextCSRF = result["csrf"] as? String, !nextCSRF.isEmpty {
            csrf = nextCSRF
        }

        let err = result["error"] as? String ?? ""
        if err == "phone_verify_required" {
            throw KTAuthError.phoneVerifyRequired
        }
        if err == "invalid_credentials" || err == "missing_credentials" {
            throw KTAuthError.invalidCredentials
        }
        if let message = result["message"] as? String, !MobileAPIMapper.boolValue(result["ok"]) {
            throw KTAuthError.unknown(message)
        }
        if !MobileAPIMapper.boolValue(result["ok"]) {
            throw KTAuthError.invalidCredentials
        }

        isLoggedIn = true
        self.username = username
        if let user = result["user"] as? [String: Any] {
            displayName = MobileAPIMapper.stringValue(user["name"], fallback: username)
        } else {
            displayName = username
        }
        await refreshBadges()
    }

    func logout() async {
        let token = csrf.isEmpty ? (try? await KTNetwork.apiAuthStatus())?["csrf"] as? String ?? "" : csrf
        if let result = try? await KTNetwork.apiLogout(csrf: token),
           let next = result["csrf"] as? String, !next.isEmpty {
            csrf = next
        }
        isLoggedIn = false
        username = ""
        displayName = ""
        unreadMessages = 0
        unreadNotifications = 0
        await refreshSession()
    }

    func refreshBadges() async {
        guard isLoggedIn else { return }
        do {
            let json = try await KTNetwork.getJSON("/api/messages.php?action=unread")
            if MobileAPIMapper.boolValue(json["ok"]) {
                unreadMessages = MobileAPIMapper.intValue(json["unread"])
                unreadNotifications = MobileAPIMapper.intValue(json["unread_notifications"])
            }
        } catch {}
    }

    func ensureCSRF() async -> String {
        if !csrf.isEmpty { return csrf }
        if let boot = try? await KTNetwork.apiAuthStatus(),
           let token = boot["csrf"] as? String, !token.isEmpty {
            csrf = token
        }
        return csrf
    }
}
