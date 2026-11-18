import Foundation
import Combine

final class HomeFeedLoader: ObservableObject {
    @Published var ads: [AdListing] = []
    @Published var isLoading = false
    @Published var isLoadingMore = false
    @Published var errorMessage: String?
    @Published var category: FeedCategory = .all
    @Published var query: String = ""
    @Published var canLoadMore = true

    private var page = 1
    private var seenIds = Set<Int>()

    @MainActor
    func refresh() async {
        page = 1
        seenIds.removeAll()
        canLoadMore = true
        errorMessage = nil
        isLoading = true
        defer { isLoading = false }

        do {
            let batch = try await fetchPage(page)
            ads = batch
            seenIds = Set(batch.map(\.id))
            canLoadMore = batch.count >= 8
        } catch {
            if ads.isEmpty {
                errorMessage = error.localizedDescription
            }
        }
    }

    @MainActor
    func loadMoreIfNeeded(current item: AdListing?) async {
        guard let item = item, canLoadMore, !isLoading, !isLoadingMore else { return }
        guard let idx = ads.firstIndex(of: item), idx >= ads.count - 4 else { return }

        isLoadingMore = true
        defer { isLoadingMore = false }

        let next = page + 1
        do {
            let batch = try await fetchPage(next)
            let fresh = batch.filter { !seenIds.contains($0.id) }
            if fresh.isEmpty {
                canLoadMore = false
                return
            }
            page = next
            for ad in fresh { seenIds.insert(ad.id) }
            ads.append(contentsOf: fresh)
            canLoadMore = batch.count >= 8
        } catch {}
    }

    @MainActor
    func apply(category: FeedCategory) async {
        self.category = category
        await refresh()
    }

    @MainActor
    func applySearch() async {
        await refresh()
    }

    private func fetchPage(_ page: Int) async throws -> [AdListing] {
        var path = category.listPath
        var queryItems: [String] = ["page=\(page)"]
        let q = query.trimmingCharacters(in: .whitespacesAndNewlines)
        if !q.isEmpty {
            let encoded = q.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? q
            queryItems.append("q=\(encoded)")
        }
        path += (path.contains("?") ? "&" : "?") + queryItems.joined(separator: "&")
        let html = try await KTNetwork.getHTML(path)
        return HomeFeedParser.parse(html: html)
    }
}

enum HomeFeedParser {
    static func parse(html: String) -> [AdListing] {
        guard let regex = try? NSRegularExpression(
            pattern: #"<article class="listing-card[\s\S]*?</article>"#,
            options: [.caseInsensitive]
        ) else { return [] }

        let range = NSRange(html.startIndex..<html.endIndex, in: html)
        let matches = regex.matches(in: html, options: [], range: range)
        var out: [AdListing] = []
        out.reserveCapacity(matches.count)

        for match in matches {
            guard let r = Range(match.range, in: html) else { continue }
            let block = String(html[r])
            guard let id = firstInt(in: block, pattern: #"data-ad-id="(\d+)""#) else { continue }
            guard let path = firstMatch(in: block, pattern: #"href="(/oglas/[^"]+)""#) else { continue }
            let title = decodeHTML(firstMatch(in: block, pattern: #"kp-list-title">([\s\S]*?)</h2>"#) ?? "Oglas")
            var price = decodeHTML(firstMatch(in: block, pattern: #"kp-list-price-main">([\s\S]*?)</span>"#) ?? "")
            if price.isEmpty {
                price = decodeHTML(firstMatch(in: block, pattern: #"(\d+[\d.,]*\s*€)"#) ?? "")
            }
            if price.isEmpty {
                if block.contains("Po dogovoru") { price = "Po dogovoru" }
                else if block.contains("Trazi se") || block.contains("Traži se") { price = "Trazi se" }
                else if block.contains(">Zamena<") { price = "Zamena" }
            }
            let location = decodeHTML(firstMatch(in: block, pattern: #"kp-list-meta-loc"[^>]*>([\s\S]*?)</span>"#) ?? "")
            let category = firstMatch(in: block, pattern: #"data-category="([^"]+)""#) ?? ""
            let imagePath = firstMatch(in: block, pattern: #"src="(/uploads/[^"]+)""#)
                ?? firstMatch(in: block, pattern: #"src="(https://kupitelefon\.rs/uploads/[^"]+)""#)
            let isTop = block.contains("listing-badge-promo") || block.contains(">TOP<")

            out.append(
                AdListing(
                    id: id,
                    title: title,
                    price: price,
                    location: location,
                    imagePath: imagePath,
                    path: path,
                    category: category,
                    isTop: isTop
                )
            )
        }
        return out
    }

    static func firstMatch(in text: String, pattern: String) -> String? {
        guard let regex = try? NSRegularExpression(pattern: pattern, options: [.dotMatchesLineSeparators, .caseInsensitive]) else {
            return nil
        }
        let range = NSRange(text.startIndex..<text.endIndex, in: text)
        guard let match = regex.firstMatch(in: text, options: [], range: range),
              match.numberOfRanges > 1,
              let r = Range(match.range(at: 1), in: text) else {
            return nil
        }
        return String(text[r])
    }

    static func firstInt(in text: String, pattern: String) -> Int? {
        guard let s = firstMatch(in: text, pattern: pattern) else { return nil }
        return Int(s)
    }

    static func decodeHTML(_ raw: String) -> String {
        var s = raw
        let map = [
            "&amp;": "&", "&quot;": "\"", "&#039;": "'", "&apos;": "'",
            "&lt;": "<", "&gt;": ">", "&nbsp;": " ", "&#39;": "'",
            "<br>": "\n", "<br/>": "\n", "<br />": "\n"
        ]
        for (k, v) in map { s = s.replacingOccurrences(of: k, with: v) }
        if let tagRegex = try? NSRegularExpression(pattern: "<[^>]+>", options: []) {
            s = tagRegex.stringByReplacingMatches(
                in: s, options: [],
                range: NSRange(s.startIndex..<s.endIndex, in: s),
                withTemplate: ""
            )
        }
        return s.replacingOccurrences(of: "\\s+", with: " ", options: .regularExpression)
            .trimmingCharacters(in: .whitespacesAndNewlines)
    }
}
