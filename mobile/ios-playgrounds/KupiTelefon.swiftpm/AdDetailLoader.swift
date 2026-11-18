import Foundation

@MainActor
final class AdDetailLoader: ObservableObject {
    @Published var detail: AdDetail?
    @Published var isLoading = false
    @Published var errorMessage: String?

    private let listing: AdListing

    init(listing: AdListing) {
        self.listing = listing
        self.detail = AdDetail(
            id: listing.id,
            title: listing.title,
            price: listing.price,
            location: listing.location,
            description: "",
            sellerName: "",
            imageURLs: listing.imageURL.map { [$0] } ?? [],
            chips: [listing.categoryLabel].filter { !$0.isEmpty },
            callURL: nil,
            messageURL: URL(string: "https://kupitelefon.rs/poruke.php"),
            favoriteURL: URL(string: "https://kupitelefon.rs/favorite.php?id=\(listing.id)"),
            reportURL: URL(string: "https://kupitelefon.rs/report.php?ad=\(listing.id)"),
            pageURL: listing.detailURL
        )
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

        var description = HomeFeedParser.decodeHTML(
            HomeFeedParser.firstMatch(in: html, pattern: #"kp-desc-body">([\s\S]*?)</div>"#)
                ?? HomeFeedParser.firstMatch(in: html, pattern: #"kp-desc-preview">([\s\S]*?)</span>"#)
                ?? ""
        )
        description = description.replacingOccurrences(of: "\n\n+", with: "\n\n", options: .regularExpression)

        var images: [URL] = []
        if let imgRegex = try? NSRegularExpression(pattern: #"kp-gallery-slide[\s\S]{0,800}?src="([^"]+)""#, options: []) {
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

        var chips: [String] = []
        if let chipRegex = try? NSRegularExpression(pattern: #"kp-keyfact">[\s\S]*?<small>([\s\S]*?)</small>[\s\S]*?<b>([\s\S]*?)</b>"#, options: []) {
            let range = NSRange(html.startIndex..<html.endIndex, in: html)
            for match in chipRegex.matches(in: html, options: [], range: range).prefix(6) {
                if match.numberOfRanges > 2,
                   let lr = Range(match.range(at: 1), in: html),
                   let vr = Range(match.range(at: 2), in: html) {
                    let label = HomeFeedParser.decodeHTML(String(html[lr]))
                    let value = HomeFeedParser.decodeHTML(String(html[vr]))
                    if !value.isEmpty {
                        chips.append(label.isEmpty ? value : "\(label) \(value)")
                    }
                }
            }
        }
        if chips.isEmpty {
            chips = [fallback.categoryLabel]
        }

        let callHref = HomeFeedParser.firstMatch(in: html, pattern: #"href="(tel:[^"]+)""#)
        let msgHref = HomeFeedParser.firstMatch(in: html, pattern: #"kp-platform-msg"[^>]*href="([^"]+)""#)
            ?? HomeFeedParser.firstMatch(in: html, pattern: #"href="(/poruke\.php[^"]*)""#)
            ?? "/poruke.php"

        return AdDetail(
            id: fallback.id,
            title: title,
            price: price,
            location: location,
            description: description,
            sellerName: seller,
            imageURLs: images,
            chips: chips,
            callURL: callHref.flatMap { URL(string: $0) },
            messageURL: AdListing.makeURL(msgHref),
            favoriteURL: URL(string: "https://kupitelefon.rs/favorite.php?id=\(fallback.id)"),
            reportURL: URL(string: "https://kupitelefon.rs/report.php?ad=\(fallback.id)"),
            pageURL: fallback.detailURL
        )
    }
}
