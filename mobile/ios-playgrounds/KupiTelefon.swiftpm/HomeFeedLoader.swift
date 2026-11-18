import Foundation

/// Čita javni HTML sa kupitelefon.rs (bez izmene sajta) i parsira listing kartice.
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
        } catch {
            // Tiho — korisnik i dalje vidi trenutnu listu.
        }
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
        var components = URLComponents(string: "https://kupitelefon.rs/")!
        var items: [URLQueryItem] = [
            URLQueryItem(name: "page", value: String(page))
        ]
        if category != .all {
            items.append(URLQueryItem(name: "type", value: category.rawValue))
        }
        let q = query.trimmingCharacters(in: .whitespacesAndNewlines)
        if !q.isEmpty {
            items.append(URLQueryItem(name: "q", value: q))
        }
        components.queryItems = items

        guard let url = components.url else {
            throw URLError(.badURL)
        }

        var request = URLRequest(url: url)
        request.setValue("KupiTelefonApp/1.0 (ios; native-feed)", forHTTPHeaderField: "User-Agent")
        request.timeoutInterval = 30

        let (data, response) = try await URLSession.shared.data(for: request)
        if let http = response as? HTTPURLResponse, !(200...299).contains(http.statusCode) {
            throw URLError(.badServerResponse)
        }

        let html = String(data: data, encoding: .utf8)
            ?? String(data: data, encoding: .isoLatin1)
            ?? ""
        return HomeFeedParser.parse(html: html)
    }
}

enum HomeFeedParser {
    static func parse(html: String) -> [AdListing] {
        let parts = html.components(separatedBy: "<article class=\"listing-card")
        guard parts.count > 1 else { return [] }

        var out: [AdListing] = []
        out.reserveCapacity(parts.count - 1)

        for chunk in parts.dropFirst() {
            let block = String(chunk.prefix(4500))
            guard let id = firstInt(in: block, pattern: #"data-ad-id="(\d+)""#) else { continue }
            guard let path = firstMatch(in: block, pattern: #"href="(/oglas/[^"]+)""#) else { continue }
            let title = decodeHTML(firstMatch(in: block, pattern: #"kp-list-title">(.*?)</h2>"#) ?? "Oglas")
            let price = decodeHTML(firstMatch(in: block, pattern: #"kp-list-price-main">(.*?)</span>"#) ?? "")
            let location = decodeHTML(firstMatch(in: block, pattern: #"kp-list-meta-loc"[^>]*>(.*?)</span>"#) ?? "")
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

    private static func firstMatch(in text: String, pattern: String) -> String? {
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

    private static func firstInt(in text: String, pattern: String) -> Int? {
        guard let s = firstMatch(in: text, pattern: pattern) else { return nil }
        return Int(s)
    }

    private static func decodeHTML(_ raw: String) -> String {
        var s = raw
        let map = [
            "&amp;": "&",
            "&quot;": "\"",
            "&#039;": "'",
            "&apos;": "'",
            "&lt;": "<",
            "&gt;": ">",
            "&nbsp;": " ",
            "&#39;": "'"
        ]
        for (k, v) in map {
            s = s.replacingOccurrences(of: k, with: v)
        }
        return s.trimmingCharacters(in: .whitespacesAndNewlines)
    }
}
