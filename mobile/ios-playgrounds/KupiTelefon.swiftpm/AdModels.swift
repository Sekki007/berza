import Foundation

struct AdListing: Identifiable, Hashable {
    let id: Int
    let title: String
    let price: String
    let location: String
    let imagePath: String?
    let path: String
    let category: String
    let isTop: Bool

    var detailURL: URL {
        URL(string: "https://kupitelefon.rs" + path)!
    }

    var imageURL: URL? {
        Self.makeURL(imagePath)
    }

    var categoryLabel: String {
        switch category {
        case "telefon": return "Telefon"
        case "delovi": return "Delovi"
        case "servis": return "Servis"
        default: return category.isEmpty ? "Oglas" : category.capitalized
        }
    }

    static func makeURL(_ path: String?) -> URL? {
        guard let path = path, !path.isEmpty else { return nil }
        if path.hasPrefix("http") {
            return URL(string: path)
        }
        return URL(string: "https://kupitelefon.rs" + path)
    }
}

struct AdDetail: Identifiable {
    let id: Int
    var title: String
    var price: String
    var location: String
    var description: String
    var sellerName: String
    var imageURLs: [URL]
    var chips: [String]
    var callURL: URL?
    var messageURL: URL?
    var favoriteURL: URL?
    var reportURL: URL?
    var pageURL: URL
}

enum FeedCategory: String, CaseIterable, Identifiable {
    case all
    case telefon
    case delovi
    case oprema
    case servis

    var id: String { rawValue }

    var title: String {
        switch self {
        case .all: return "Sve"
        case .telefon: return "Telefoni"
        case .delovi: return "Delovi"
        case .oprema: return "Oprema"
        case .servis: return "Servisi"
        }
    }

    var listPath: String {
        switch self {
        case .all: return "/"
        case .telefon: return "/oglasi/telefoni"
        case .delovi: return "/oglasi/delovi"
        case .oprema: return "/oglasi/oprema"
        case .servis: return "/oglasi/servis"
        }
    }
}
