import Foundation

@MainActor
final class AdDetailLoader: ObservableObject {
    @Published var detail: AdDetail?
    @Published var isLoading = false
    @Published var errorMessage: String?

    private let listing: AdListing

    init(listing: AdListing) {
        self.listing = listing
        self.detail = Self.placeholder(from: listing)
    }

    func load() async {
        isLoading = true
        errorMessage = nil
        defer { isLoading = false }

        do {
            var request = URLRequest(url: listing.detailURL)
            request.setValue("KupiTelefonApp/1.0 (ios; native-detail)", forHTTPHeaderField: "User-Agent")
            request.timeoutInterval = 30
            let (data, response) = try await URLSession.shared.data(for: request)
            if let http = response as? HTTPURLResponse, !(200...299).contains(http.statusCode) {
                throw URLError(.badServerResponse)
            }
            let html = String(data: data, encoding: .utf8)
                ?? String(data: data, encoding: .isoLatin1)
                ?? ""
            detail = AdDetailParser.parse(html: html, fallback: listing)
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    static func placeholder(from listing: AdListing) -> AdDetail {
        AdDetail(
            id: listing.id,
            title: listing.title,
            price: listing.price,
            priceNote: "",
            location: listing.location,
            description: "",
            sellerName: "",
            sellerMeta: "",
            imageURLs: listing.imageURL.map { [$0] } ?? [],
            facts: [],
            chips: [listing.categoryLabel].filter { !$0.isEmpty },
            views: "",
            callURL: nil,
            viberURL: nil,
            whatsappURL: nil,
            messageURL: URL(string: "https://kupitelefon.rs/poruke.php"),
            shopURL: nil,
            favoriteURL: URL(string: "https://kupitelefon.rs/favorite.php?id=\(listing.id)"),
            reportURL: URL(string: "https://kupitelefon.rs/report.php?ad=\(listing.id)"),
            pageURL: listing.detailURL
        )
    }
}

enum AdDetailParser {
    static func parse(html: String, fallback: AdListing) -> AdDetail {
        let title = HomeFeedParser.decodeHTML(
            HomeFeedParser.firstMatch(in: html, pattern: #"id="ad-title"[^>]*>([\s\S]*?)</h1>"#)
                ?? HomeFeedParser.firstMatch(in: html, pattern: #"kp-listing-title">([\s\S]*?)</h1>"#)
                ?? fallback.title
        )

        var price = HomeFeedParser.decodeHTML(
            HomeFeedParser.firstMatch(in: html, pattern: #"class="kp-price[^"]*"[^>]*>([\s\S]*?)</div>"#)
                ?? ""
        )
        if price.isEmpty { price = fallback.price }
        if price.isEmpty {
            price = HomeFeedParser.decodeHTML(HomeFeedParser.firstMatch(in: html, pattern: #"(\d+[\d.,]*\s*€)"#) ?? "")
        }

        let priceNote = HomeFeedParser.decodeHTML(
            HomeFeedParser.firstMatch(in: html, pattern: #"kp-price-rsd">([\s\S]*?)</span>"#)
                ?? HomeFeedParser.firstMatch(in: html, pattern: #"kp-price-note">([\s\S]*?)</span>"#)
                ?? ""
        )

        let location = HomeFeedParser.decodeHTML(
            HomeFeedParser.firstMatch(in: html, pattern: #"oglasi\?location=([^"&]+)"#)
                .map { $0.removingPercentEncoding ?? $0 }
                ?? fallback.location
        )

        let seller = HomeFeedParser.decodeHTML(
            HomeFeedParser.firstMatch(in: html, pattern: #"kp-seller-name-link"[^>]*>([\s\S]*?)</a>"#)
                ?? HomeFeedParser.firstMatch(in: html, pattern: #"kp-seller-name-plain">([\s\S]*?)</span>"#)
                ?? ""
        )

        let sellerMeta = HomeFeedParser.decodeHTML(
            HomeFeedParser.firstMatch(in: html, pattern: #"kp-seller-meta-line">([\s\S]*?)</p>"#) ?? ""
        )

        let shopPath = HomeFeedParser.firstMatch(in: html, pattern: #"kp-seller-name-link"[^>]*href="([^"]+)""#)
            ?? HomeFeedParser.firstMatch(in: html, pattern: #"kp-seller-link"[^>]*href="([^"]+)""#)

        var description = HomeFeedParser.decodeHTML(
            HomeFeedParser.firstMatch(in: html, pattern: #"kp-desc-body">([\s\S]*?)</div>"#)
                ?? HomeFeedParser.firstMatch(in: html, pattern: #"kp-desc-preview">([\s\S]*?)</span>"#)
                ?? ""
        )
        description = description.replacingOccurrences(of: "\n\n+", with: "\n\n", options: .regularExpression)

        var images: [URL] = []
        if let imgRegex = try? NSRegularExpression(pattern: #"kp-gallery-slide[\s\S]{0,900}?src="([^"]+)""#, options: []) {
            let range = NSRange(html.startIndex..<html.endIndex, in: html)
            for match in imgRegex.matches(in: html, options: [], range: range) {
                if match.numberOfRanges > 1, let r = Range(match.range(at: 1), in: html) {
                    if let url = AdListing.makeURL(String(html[r])), !images.contains(url) {
                        images.append(url)
                    }
                }
            }
        }
        if images.isEmpty, let u = fallback.imageURL {
            images = [u]
        }

        var facts: [AdFact] = []
        if let chipRegex = try? NSRegularExpression(
            pattern: #"kp-keyfact">[\s\S]*?<small>([\s\S]*?)</small>[\s\S]*?<b>([\s\S]*?)</b>"#,
            options: []
        ) {
            let range = NSRange(html.startIndex..<html.endIndex, in: html)
            for match in chipRegex.matches(in: html, options: [], range: range).prefix(8) {
                if match.numberOfRanges > 2,
                   let lr = Range(match.range(at: 1), in: html),
                   let vr = Range(match.range(at: 2), in: html) {
                    let label = HomeFeedParser.decodeHTML(String(html[lr]))
                    let value = HomeFeedParser.decodeHTML(String(html[vr]))
                    if !value.isEmpty {
                        facts.append(AdFact(label: label, value: value))
                    }
                }
            }
        }

        var chips: [String] = []
        if let kwRegex = try? NSRegularExpression(pattern: #"kp-chip-kw[^>]*>[\s\S]*?<a[^>]*>([\s\S]*?)</a>"#, options: []) {
            let range = NSRange(html.startIndex..<html.endIndex, in: html)
            for match in kwRegex.matches(in: html, options: [], range: range).prefix(8) {
                if match.numberOfRanges > 1, let r = Range(match.range(at: 1), in: html) {
                    let t = HomeFeedParser.decodeHTML(String(html[r]))
                    if !t.isEmpty { chips.append(t) }
                }
            }
        }
        if chips.isEmpty {
            chips = [fallback.categoryLabel]
        }

        let views = HomeFeedParser.decodeHTML(
            HomeFeedParser.firstMatch(in: html, pattern: #"kp-head-stats">[\s\S]*?(\d+)[\s\S]*?</span>"#) ?? ""
        )

        let callHref = HomeFeedParser.firstMatch(in: html, pattern: #"href="(tel:[^"]+)""#)
        let viberHref = HomeFeedParser.firstMatch(in: html, pattern: #"href="((?:viber|https):[^"]*viber[^"]*)""#)
            ?? HomeFeedParser.firstMatch(in: html, pattern: #"data-contact-channel="viber"[\s\S]{0,120}?href="([^"]+)""#)
        let waHref = HomeFeedParser.firstMatch(in: html, pattern: #"href="(https://wa\.me/[^"]+)""#)
            ?? HomeFeedParser.firstMatch(in: html, pattern: #"data-contact-channel="whatsapp"[\s\S]{0,120}?href="([^"]+)""#)
        let msgHref = HomeFeedParser.firstMatch(in: html, pattern: #"class="kp-platform-msg"[^>]*href="([^"]+)""#)
            ?? HomeFeedParser.firstMatch(in: html, pattern: #"href="(/poruke\.php[^"]*)""#)
            ?? "/poruke.php"

        return AdDetail(
            id: fallback.id,
            title: title,
            price: price,
            priceNote: priceNote,
            location: location,
            description: description,
            sellerName: seller,
            sellerMeta: sellerMeta,
            imageURLs: images,
            facts: facts,
            chips: chips,
            views: views,
            callURL: callHref.flatMap { URL(string: $0.replacingOccurrences(of: " ", with: "")) },
            viberURL: viberHref.flatMap { AdListing.makeURL($0) },
            whatsappURL: waHref.flatMap { AdListing.makeURL($0) },
            messageURL: AdListing.makeURL(msgHref),
            shopURL: AdListing.makeURL(shopPath),
            favoriteURL: URL(string: "https://kupitelefon.rs/favorite.php?id=\(fallback.id)"),
            reportURL: URL(string: "https://kupitelefon.rs/report.php?ad=\(fallback.id)"),
            pageURL: fallback.detailURL
        )
    }
}
