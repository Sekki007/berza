import SwiftUI

enum Brand {
    static let green = Color(red: 0.239, green: 0.604, blue: 0.314)
    static let greenDark = Color(red: 0.176, green: 0.478, blue: 0.243)
    static let yellow = Color(red: 0.961, green: 0.773, blue: 0.094)
    static let price = Color(red: 0.753, green: 0.224, blue: 0.169)
    static let bg = Color(red: 0.949, green: 0.949, blue: 0.949)
    static let border = Color(red: 0.867, green: 0.867, blue: 0.867)
    static let text = Color(red: 0.2, green: 0.2, blue: 0.2)
    static let muted = Color(red: 0.4, green: 0.4, blue: 0.4)

    static let site = URL(string: "https://kupitelefon.rs")!
}

struct BrandLogo: View {
    var size: CGFloat = 22

    var body: some View {
        HStack(spacing: 0) {
            Text("Kupi")
                .foregroundColor(Brand.green)
            Text("Telefon")
                .foregroundColor(Brand.text)
        }
        .font(.system(size: size, weight: .heavy, design: .rounded))
    }
}

struct SiteLink: Identifiable, Hashable {
    let title: String
    let url: URL

    var id: String { title + "|" + url.absoluteString }

    init(_ title: String, path: String) {
        self.title = title
        if path.hasPrefix("http") {
            self.url = URL(string: path)!
        } else {
            self.url = URL(string: "https://kupitelefon.rs" + path)!
        }
    }
}
