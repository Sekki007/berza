import SwiftUI

final class AdDetailLoader: ObservableObject {
    @Published var detail: AdDetail?
    @Published var isLoading = false
    @Published var errorMessage: String?
    @Published var isFavorite = false

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
            let json = try await KTNetwork.apiAdDetail(id: listing.id)
            guard MobileAPIMapper.boolValue(json["ok"]) else {
                errorMessage = MobileAPIMapper.stringValue(json["error"], fallback: "Oglas nije pronadjen.")
                return
            }
            detail = MobileAPIMapper.detail(from: json, fallback: listing)
            if let ad = json["ad"] as? [String: Any] {
                isFavorite = MobileAPIMapper.boolValue(ad["favorite"])
            }
            if let csrf = json["csrf"] as? String, !csrf.isEmpty {
                // Session may pick this up later via refresh; store is optional.
            }
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
            partnerId: 0,
            shopURL: nil,
            favoriteURL: URL(string: "https://kupitelefon.rs/favorite.php?id=\(listing.id)"),
            reportURL: URL(string: "https://kupitelefon.rs/report.php?ad=\(listing.id)"),
            pageURL: listing.detailURL
        )
    }
}
