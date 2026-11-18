import Foundation

enum KTNetwork {
    static let base = URL(string: "https://kupitelefon.rs")!

    private static let jar = HTTPCookieStorage.shared
    private static let session: URLSession = {
        let cfg = URLSessionConfiguration.default
        cfg.httpCookieStorage = jar
        cfg.httpCookieAcceptPolicy = .always
        cfg.httpShouldSetCookies = true
        cfg.timeoutIntervalForRequest = 30
        return URLSession(configuration: cfg)
    }()

    static func url(_ path: String) -> URL {
        if path.hasPrefix("http") { return URL(string: path)! }
        return URL(string: path, relativeTo: base)!.absoluteURL
    }

    static func getHTML(_ path: String) async throws -> String {
        var req = URLRequest(url: url(path))
        req.setValue("KupiTelefonApp/1.0 (ios; native)", forHTTPHeaderField: "User-Agent")
        let (data, resp) = try await session.data(for: req)
        guard let http = resp as? HTTPURLResponse, (200...399).contains(http.statusCode) else {
            throw URLError(.badServerResponse)
        }
        return String(data: data, encoding: .utf8)
            ?? String(data: data, encoding: .isoLatin1)
            ?? ""
    }

    static func getJSON(_ path: String) async throws -> Any {
        var req = URLRequest(url: url(path))
        req.setValue("KupiTelefonApp/1.0 (ios; native)", forHTTPHeaderField: "User-Agent")
        req.setValue("application/json", forHTTPHeaderField: "Accept")
        let (data, resp) = try await session.data(for: req)
        guard let http = resp as? HTTPURLResponse else { throw URLError(.badServerResponse) }
        if http.statusCode == 401 { throw KTAuthError.loginRequired }
        guard (200...299).contains(http.statusCode) else { throw URLError(.badServerResponse) }
        return try JSONSerialization.jsonObject(with: data)
    }

    static func extractCSRF(from html: String) -> String? {
        HomeFeedParser.firstMatch(in: html, pattern: #"name="csrf-token"\s+content="([^"]+)""#)
            ?? HomeFeedParser.firstMatch(in: html, pattern: #"name="_csrf"\s+value="([^"]+)""#)
            ?? HomeFeedParser.firstMatch(in: html, pattern: #"name="_csrf"\s+value='([^']+)'"#)
    }

    static func postForm(path: String, fields: [String: String], csrf: String?) async throws -> (status: Int, html: String, finalURL: URL?) {
        var bodyParts: [String] = []
        var all = fields
        if let csrf = csrf, !csrf.isEmpty {
            all["_csrf"] = csrf
        }
        for (k, v) in all {
            let ek = k.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? k
            let ev = v.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? v
            bodyParts.append("\(ek)=\(ev)")
        }
        let body = bodyParts.joined(separator: "&").data(using: .utf8)

        var req = URLRequest(url: url(path))
        req.httpMethod = "POST"
        req.httpBody = body
        req.setValue("application/x-www-form-urlencoded; charset=utf-8", forHTTPHeaderField: "Content-Type")
        req.setValue("KupiTelefonApp/1.0 (ios; native)", forHTTPHeaderField: "User-Agent")
        if let csrf = csrf {
            req.setValue(csrf, forHTTPHeaderField: "X-CSRF-Token")
        }

        let (data, resp) = try await session.data(for: req)
        let http = resp as? HTTPURLResponse
        let html = String(data: data, encoding: .utf8)
            ?? String(data: data, encoding: .isoLatin1)
            ?? ""
        return (http?.statusCode ?? 0, html, http?.url)
    }

    static func postJSON(path: String, fields: [String: String], csrf: String?) async throws -> [String: Any] {
        var req = URLRequest(url: url(path))
        req.httpMethod = "POST"
        var all = fields
        if let csrf = csrf { all["_csrf"] = csrf }
        let body = all.map { key, value in
            let ek = key.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? key
            let ev = value.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? value
            return "\(ek)=\(ev)"
        }.joined(separator: "&")
        req.httpBody = body.data(using: .utf8)
        req.setValue("application/x-www-form-urlencoded; charset=utf-8", forHTTPHeaderField: "Content-Type")
        req.setValue("application/json", forHTTPHeaderField: "Accept")
        req.setValue("KupiTelefonApp/1.0 (ios; native)", forHTTPHeaderField: "User-Agent")
        if let csrf = csrf {
            req.setValue(csrf, forHTTPHeaderField: "X-CSRF-Token")
        }
        let (data, resp) = try await session.data(for: req)
        if let http = resp as? HTTPURLResponse, http.statusCode == 401 {
            throw KTAuthError.loginRequired
        }
        let obj = try JSONSerialization.jsonObject(with: data)
        return obj as? [String: Any] ?? [:]
    }
}

enum KTAuthError: LocalizedError {
    case loginRequired
    case invalidCredentials
    case phoneVerifyRequired
    case unknown(String)

    var errorDescription: String? {
        switch self {
        case .loginRequired: return "Potrebna je prijava."
        case .invalidCredentials: return "Pogresno korisnicko ime ili lozinka."
        case .phoneVerifyRequired: return "Potvrdi broj telefona SMS kodom na sajtu."
        case .unknown(let s): return s
        }
    }
}
