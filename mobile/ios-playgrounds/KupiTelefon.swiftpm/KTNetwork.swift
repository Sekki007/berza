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

    static func getJSON(_ path: String) async throws -> [String: Any] {
        var req = URLRequest(url: url(path))
        req.setValue("KupiTelefonApp/1.0 (ios; native)", forHTTPHeaderField: "User-Agent")
        req.setValue("application/json", forHTTPHeaderField: "Accept")
        let (data, resp) = try await session.data(for: req)
        guard let http = resp as? HTTPURLResponse else { throw URLError(.badServerResponse) }
        if http.statusCode == 401 { throw KTAuthError.loginRequired }
        guard (200...299).contains(http.statusCode) else {
            if let obj = try? JSONSerialization.jsonObject(with: data) as? [String: Any] {
                if let err = obj["error"] as? String {
                    throw KTAuthError.unknown(err)
                }
            }
            throw URLError(.badServerResponse)
        }
        let obj = try JSONSerialization.jsonObject(with: data)
        return obj as? [String: Any] ?? [:]
    }

    static func postFormJSON(path: String, fields: [String: String], csrf: String?) async throws -> [String: Any] {
        var all = fields
        if let csrf = csrf, !csrf.isEmpty {
            all["_csrf"] = csrf
        }
        let body = all.map { key, value in
            let ek = key.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? key
            let ev = value.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? value
            return "\(ek)=\(ev)"
        }.joined(separator: "&")

        var req = URLRequest(url: url(path))
        req.httpMethod = "POST"
        req.httpBody = body.data(using: .utf8)
        req.setValue("application/x-www-form-urlencoded; charset=utf-8", forHTTPHeaderField: "Content-Type")
        req.setValue("application/json", forHTTPHeaderField: "Accept")
        req.setValue("KupiTelefonApp/1.0 (ios; native)", forHTTPHeaderField: "User-Agent")
        if let csrf = csrf, !csrf.isEmpty {
            req.setValue(csrf, forHTTPHeaderField: "X-CSRF-Token")
        }

        let (data, resp) = try await session.data(for: req)
        let http = resp as? HTTPURLResponse
        let obj = (try? JSONSerialization.jsonObject(with: data)) as? [String: Any] ?? [:]
        if http?.statusCode == 401 { throw KTAuthError.loginRequired }
        return obj
    }

    // MARK: - Mobile API

    static func apiAuthStatus() async throws -> [String: Any] {
        try await getJSON("/api/mobile_auth.php?action=status")
    }

    static func apiLogin(username: String, password: String, csrf: String) async throws -> [String: Any] {
        try await postFormJSON(
            path: "/api/mobile_auth.php?action=login",
            fields: [
                "action": "login",
                "username": username,
                "password": password,
                "remember_me": "1"
            ],
            csrf: csrf
        )
    }

    static func apiLogout(csrf: String) async throws -> [String: Any] {
        try await postFormJSON(
            path: "/api/mobile_auth.php?action=logout",
            fields: ["action": "logout"],
            csrf: csrf
        )
    }

    static func apiAds(query: String) async throws -> [String: Any] {
        try await getJSON("/api/mobile_ads.php?\(query)")
    }

    static func apiAdDetail(id: Int) async throws -> [String: Any] {
        try await getJSON("/api/mobile_ads.php?id=\(id)")
    }

    static func apiFavorites() async throws -> [String: Any] {
        try await getJSON("/api/mobile_favorites.php")
    }

    static func apiToggleFavorite(adId: Int, csrf: String) async throws -> [String: Any] {
        try await postFormJSON(
            path: "/api/mobile_favorites.php",
            fields: ["ad_id": "\(adId)"],
            csrf: csrf
        )
    }

    static func apiCreateBootstrap() async throws -> [String: Any] {
        try await getJSON("/api/mobile_ad_create.php")
    }

    static func apiCreateAd(fields: [String: String], csrf: String) async throws -> [String: Any] {
        try await postFormJSON(path: "/api/mobile_ad_create.php", fields: fields, csrf: csrf)
    }

    // Legacy helpers still used by messages HTML/JSON bridges
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
        try await postFormJSON(path: path, fields: fields, csrf: csrf)
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

enum MobileAPIMapper {
    static func listing(from dict: [String: Any]) -> AdListing? {
        let id = intValue(dict["id"])
        guard id > 0 else { return nil }
        let path = stringValue(dict["path"]).isEmpty ? "/oglas.php?id=\(id)" : stringValue(dict["path"])
        return AdListing(
            id: id,
            title: stringValue(dict["title"]).isEmpty ? "Oglas" : stringValue(dict["title"]),
            price: stringValue(dict["price"]),
            location: stringValue(dict["location"]),
            imagePath: optionalString(dict["image"]),
            path: path,
            category: stringValue(dict["type"]),
            isTop: boolValue(dict["is_top"])
        )
    }

    static func listings(from payload: [String: Any]) -> [AdListing] {
        let raw = payload["items"] as? [[String: Any]] ?? []
        return raw.compactMap(listing(from:))
    }

    static func detail(from payload: [String: Any], fallback: AdListing) -> AdDetail {
        let ad = payload["ad"] as? [String: Any] ?? [:]
        let id = intValue(ad["id"], fallback: fallback.id)
        let title = stringValue(ad["title"], fallback: fallback.title)
        let price = stringValue(ad["price"], fallback: fallback.price)
        let priceNote = stringValue(ad["price_note"])
        let location = stringValue(ad["location"], fallback: fallback.location)
        let description = stringValue(ad["description"])

        var images: [URL] = []
        if let arr = ad["images"] as? [String] {
            for s in arr {
                if let u = AdListing.makeURL(s), !images.contains(u) {
                    images.append(u)
                }
            }
        }
        if images.isEmpty, let u = AdListing.makeURL(optionalString(ad["image"]) ?? fallback.imagePath) {
            images = [u]
        }

        var facts: [AdFact] = []
        if let factArr = ad["facts"] as? [[String: Any]] {
            for f in factArr {
                let label = stringValue(f["label"])
                let value = stringValue(f["value"])
                if !value.isEmpty {
                    facts.append(AdFact(label: label, value: value))
                }
            }
        }

        var chips: [String] = []
        if let chipArr = ad["chips"] as? [String] {
            chips = chipArr.filter { !$0.isEmpty }
        }
        if chips.isEmpty { chips = [fallback.categoryLabel] }

        let seller = ad["seller"] as? [String: Any]
        let sellerName = stringValue(seller?["name"])
        let sellerMetaParts = [
            stringValue(seller?["location"]),
            boolValue(seller?["verified_phone"]) ? "Verifikovan telefon" : ""
        ].filter { !$0.isEmpty }
        let partnerId = intValue(ad["partner_id"], fallback: intValue(seller?["id"]))

        let viewsNum = intValue(ad["views"])
        let views = viewsNum > 0 ? "\(viewsNum)" : stringValue(ad["relative"])

        let callURL = AdListing.makeURL(optionalString(ad["call_url"]))
        let messagePath = optionalString(ad["message_path"]) ?? "/poruke.php"
        let pagePath = stringValue(ad["path"], fallback: fallback.path)
        let pageURL = AdListing.makeURL(pagePath) ?? fallback.detailURL

        return AdDetail(
            id: id,
            title: title,
            price: price,
            priceNote: priceNote,
            location: location,
            description: description,
            sellerName: sellerName,
            sellerMeta: sellerMetaParts.joined(separator: " · "),
            imageURLs: images,
            facts: facts,
            chips: chips,
            views: views,
            callURL: callURL,
            viberURL: nil,
            whatsappURL: nil,
            messageURL: AdListing.makeURL(messagePath),
            partnerId: partnerId,
            shopURL: partnerId > 0 ? AdListing.makeURL("/prodavac.php?id=\(partnerId)") : nil,
            favoriteURL: URL(string: "https://kupitelefon.rs/favorite.php?id=\(id)"),
            reportURL: URL(string: "https://kupitelefon.rs/report.php?ad=\(id)"),
            pageURL: pageURL
        )
    }

    static func stringValue(_ any: Any?, fallback: String = "") -> String {
        if let s = any as? String { return s }
        if let n = any as? NSNumber { return n.stringValue }
        return fallback
    }

    static func optionalString(_ any: Any?) -> String? {
        let s = stringValue(any)
        return s.isEmpty ? nil : s
    }

    static func intValue(_ any: Any?, fallback: Int = 0) -> Int {
        if let i = any as? Int { return i }
        if let n = any as? NSNumber { return n.intValue }
        if let s = any as? String, let i = Int(s) { return i }
        return fallback
    }

    static func boolValue(_ any: Any?) -> Bool {
        if let b = any as? Bool { return b }
        if let n = any as? NSNumber { return n.boolValue }
        if let s = any as? String {
            return s == "1" || s.lowercased() == "true"
        }
        return false
    }
}
