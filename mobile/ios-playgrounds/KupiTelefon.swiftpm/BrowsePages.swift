import SwiftUI

struct CatalogBrowseView: View {
    @State private var items: [CatalogItem] = []
    @State private var loading = false
    @State private var query = ""

    var body: some View {
        VStack(spacing: 0) {
            searchField
            if loading && items.isEmpty {
                ProgressView().tint(Brand.green).frame(maxWidth: .infinity, maxHeight: .infinity)
            } else {
                List(filtered) { item in
                    NavigationLink(value: item) {
                        VStack(alignment: .leading, spacing: 4) {
                            Text(item.title)
                                .font(.system(size: 15, weight: .semibold))
                            if !item.subtitle.isEmpty {
                                Text(item.subtitle)
                                    .font(.caption)
                                    .foregroundColor(Brand.muted)
                            }
                        }
                        .padding(.vertical, 2)
                    }
                }
                .listStyle(.plain)
            }
        }
        .background(Brand.bg.ignoresSafeArea())
        .navigationTitle("Katalog")
        .navigationDestination(for: CatalogItem.self) { item in
            NativeListingsPage(title: item.title, path: item.path)
        }
        .task { await load() }
    }

    private var filtered: [CatalogItem] {
        let q = query.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()
        if q.isEmpty { return items }
        return items.filter { $0.title.lowercased().contains(q) || $0.subtitle.lowercased().contains(q) }
    }

    private var searchField: some View {
        HStack {
            Image(systemName: "magnifyingglass").foregroundColor(Brand.muted)
            TextField("Pretrazi katalog", text: $query)
                .textInputAutocapitalization(.never)
        }
        .padding(12)
        .background(Color.white)
    }

    private func load() async {
        loading = true
        defer { loading = false }
        do {
            let html = try await KTNetwork.getHTML("/katalog")
            items = CatalogParser.parse(html: html)
        } catch {
            items = []
        }
    }
}

struct CatalogItem: Identifiable, Hashable {
    let id: String
    let title: String
    let subtitle: String
    let path: String
}

enum CatalogParser {
    static func parse(html: String) -> [CatalogItem] {
        var out: [CatalogItem] = []
        if let regex = try? NSRegularExpression(pattern: #"href="(/katalog/[^"]+)"[^>]*>([\s\S]*?)</a>"#, options: []) {
            let range = NSRange(html.startIndex..<html.endIndex, in: html)
            var seen = Set<String>()
            for match in regex.matches(in: html, options: [], range: range) {
                guard match.numberOfRanges > 2,
                      let pr = Range(match.range(at: 1), in: html),
                      let tr = Range(match.range(at: 2), in: html) else { continue }
                let path = String(html[pr])
                if path == "/katalog" || seen.contains(path) { continue }
                seen.insert(path)
                let title = HomeFeedParser.decodeHTML(String(html[tr]))
                if title.count < 2 { continue }
                let parts = path.split(separator: "/").map(String.init)
                let subtitle = parts.count >= 3 ? parts[2].replacingOccurrences(of: "-", with: " ") : ""
                out.append(CatalogItem(id: path, title: title, subtitle: subtitle, path: path))
                if out.count >= 200 { break }
            }
        }
        return out
    }
}

struct ServicesBrowseView: View {
    @State private var cities: [CatalogItem] = []
    @State private var loading = false

    var body: some View {
        Group {
            if loading && cities.isEmpty {
                ProgressView().tint(Brand.green)
            } else {
                List(cities) { city in
                    NavigationLink(value: city) {
                        Text(city.title).font(.system(size: 16, weight: .semibold))
                    }
                }
                .listStyle(.plain)
            }
        }
        .navigationTitle("Servisi")
        .navigationDestination(for: CatalogItem.self) { city in
            NativeListingsPage(title: city.title, path: city.path)
        }
        .task { await load() }
    }

    private func load() async {
        loading = true
        defer { loading = false }
        do {
            let html = try await KTNetwork.getHTML("/servisi")
            var out: [CatalogItem] = []
            if let regex = try? NSRegularExpression(pattern: #"href="(/servisi/[^"]+)"[^>]*>([\s\S]*?)</a>"#, options: []) {
                let range = NSRange(html.startIndex..<html.endIndex, in: html)
                var seen = Set<String>()
                for match in regex.matches(in: html, options: [], range: range) {
                    guard match.numberOfRanges > 2,
                          let pr = Range(match.range(at: 1), in: html),
                          let tr = Range(match.range(at: 2), in: html) else { continue }
                    let path = String(html[pr])
                    if seen.contains(path) || path == "/servisi" { continue }
                    seen.insert(path)
                    let title = HomeFeedParser.decodeHTML(String(html[tr]))
                    if title.count < 2 { continue }
                    out.append(CatalogItem(id: path, title: title, subtitle: "", path: path))
                }
            }
            cities = out
        } catch {
            cities = []
        }
    }
}

/// Genericka native lista oglasa sa bilo kog listing URL-a.
struct NativeListingsPage: View {
    let title: String
    let path: String
    @State private var ads: [AdListing] = []
    @State private var loading = false

    var body: some View {
        Group {
            if loading && ads.isEmpty {
                ProgressView().tint(Brand.green)
            } else if ads.isEmpty {
                Text("Nema rezultata.")
                    .foregroundColor(Brand.muted)
            } else {
                ScrollView {
                    LazyVStack(spacing: 12) {
                        ForEach(ads) { ad in
                            NavigationLink(value: ad) {
                                AdCardView(ad: ad)
                            }
                            .buttonStyle(.plain)
                        }
                    }
                    .padding(14)
                }
            }
        }
        .background(Brand.bg.ignoresSafeArea())
        .navigationTitle(title)
        .navigationDestination(for: AdListing.self) { ad in
            AdDetailView(listing: ad)
        }
        .task { await load() }
        .refreshable { await load() }
    }

    private func load() async {
        loading = true
        defer { loading = false }
        do {
            let html = try await KTNetwork.getHTML(path)
            ads = HomeFeedParser.parse(html: html)
        } catch {
            ads = []
        }
    }
}

struct MyAdsView: View {
    @EnvironmentObject private var session: AppSession
    @State private var ads: [AdListing] = []
    @State private var loading = false
    @State private var showLogin = false

    var body: some View {
        Group {
            if !session.isLoggedIn {
                VStack(spacing: 12) {
                    Text("Prijavi se za svoje oglase")
                    Button("Prijava") { showLogin = true }
                        .buttonStyle(.borderedProminent).tint(Brand.green)
                }
            } else if loading && ads.isEmpty {
                ProgressView().tint(Brand.green)
            } else if ads.isEmpty {
                Text("Nemas aktivnih oglasa u listi.")
                    .foregroundColor(Brand.muted)
            } else {
                ScrollView {
                    LazyVStack(spacing: 12) {
                        ForEach(ads) { ad in
                            NavigationLink(value: ad) { AdCardView(ad: ad) }
                                .buttonStyle(.plain)
                        }
                    }
                    .padding(14)
                }
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Brand.bg.ignoresSafeArea())
        .navigationTitle("Moji oglasi")
        .navigationDestination(for: AdListing.self) { ad in
            AdDetailView(listing: ad)
        }
        .sheet(isPresented: $showLogin) { NavigationStack { LoginView().environmentObject(session) } }
        .task { if session.isLoggedIn { await load() } }
    }

    private func load() async {
        loading = true
        defer { loading = false }
        do {
            let html = try await KTNetwork.getHTML("/nalog.php?tab=oglasi")
            ads = HomeFeedParser.parse(html: html)
        } catch {
            ads = []
        }
    }
}

struct StaticInfoView: View {
    let title: String
    let path: String
    @State private var text = "Ucitavanje..."

    var body: some View {
        ScrollView {
            Text(text)
                .font(.system(size: 15))
                .foregroundColor(Brand.text)
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(16)
        }
        .background(Brand.bg.ignoresSafeArea())
        .navigationTitle(title)
        .task {
            do {
                let html = try await KTNetwork.getHTML(path)
                // Rough extract of main content
                let raw = HomeFeedParser.firstMatch(in: html, pattern: #"<main[\s\S]*?>([\s\S]*?)</main>"#)
                    ?? HomeFeedParser.firstMatch(in: html, pattern: #"class="[^"]*content[^"]*"[^>]*>([\s\S]*?)</div>"#)
                    ?? html
                text = HomeFeedParser.decodeHTML(raw)
                if text.count > 8000 {
                    text = String(text.prefix(8000)) + "…"
                }
            } catch {
                text = error.localizedDescription
            }
        }
    }
}
