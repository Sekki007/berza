import SwiftUI

struct SearchHubView: View {
    @State private var openLink: SiteLink?

    var body: some View {
        NavigationStack {
            List {
                Section("Brza pretraga") {
                    hubRow("Sva pretraga", icon: "magnifyingglass", path: "/#search")
                    hubRow("Filteri oglasa", icon: "slider.horizontal.3", path: "/oglasi")
                    hubRow("Samo telefoni", icon: "iphone", path: "/oglasi/telefoni")
                    hubRow("Delovi", icon: "wrench.and.screwdriver", path: "/oglasi/delovi")
                    hubRow("Oprema", icon: "headphones", path: "/oglasi/oprema")
                    hubRow("Servisi", icon: "screwdriver", path: "/oglasi/servis")
                }
                Section("Istrazi") {
                    hubRow("Katalog telefona", icon: "book", path: "/katalog")
                    hubRow("Servisi / firme", icon: "building.2", path: "/servisi")
                    hubRow("Provera IMEI", icon: "checkmark.shield", path: "/provera-imei")
                }
            }
            .listStyle(.insetGrouped)
            .navigationTitle("Pretraga")
            .sheet(item: $openLink) { link in
                SiteBrowserSheet(link: link)
            }
        }
    }

    private func hubRow(_ title: String, icon: String, path: String) -> some View {
        Button {
            openLink = SiteLink(title, path: path)
        } label: {
            Label(title, systemImage: icon)
                .foregroundColor(Brand.text)
        }
    }
}

struct MessagesHubView: View {
    var body: some View {
        NavigationStack {
            SiteBrowserView(title: "Poruke", url: URL(string: "https://kupitelefon.rs/poruke.php")!)
        }
    }
}

struct PostAdHubView: View {
    var body: some View {
        NavigationStack {
            SiteBrowserView(title: "Besplatno", url: URL(string: "https://kupitelefon.rs/postavi-oglas")!)
        }
    }
}

struct AccountHubView: View {
    @State private var openLink: SiteLink?

    private let rows: [(String, String, String)] = [
        ("Pregled", "square.grid.2x2", "/nalog.php?tab=pregled"),
        ("Moji oglasi", "tag", "/nalog.php?tab=oglasi"),
        ("Poruke", "bubble.left.and.bubble.right", "/poruke.php"),
        ("Omiljeni", "heart", "/favorites.php"),
        ("Obavestenja", "bell", "/nalog.php?tab=obavestenja"),
        ("Sacuvane pretrage", "bookmark", "/nalog.php?tab=pretrage"),
        ("Krediti / TOP", "star.circle", "/nalog.php?tab=krediti"),
        ("Profil / izlog", "person.crop.circle", "/nalog.php?tab=profil"),
        ("Statistika", "chart.bar", "/nalog.php?tab=statistika"),
        ("Podesavanja", "gearshape", "/nalog.php?tab=podesavanja"),
        ("Katalog", "book", "/katalog"),
        ("Servisi", "building.2", "/servisi"),
        ("Provera IMEI", "checkmark.shield", "/provera-imei"),
        ("Kako radi", "questionmark.circle", "/kako-radi"),
        ("Uslovi", "doc.text", "/uslovi"),
        ("Privatnost", "hand.raised", "/privatnost"),
        ("Prijava", "arrow.right.circle", "/prijava"),
    ]

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 14) {
                    VStack(spacing: 8) {
                        ZStack {
                            Circle()
                                .fill(Brand.green.opacity(0.15))
                                .frame(width: 72, height: 72)
                            Image(systemName: "person.fill")
                                .font(.system(size: 28))
                                .foregroundColor(Brand.green)
                        }
                        BrandLogo(size: 20)
                        Text("Nalog i prodavac alati")
                            .font(.subheadline)
                            .foregroundColor(Brand.muted)
                    }
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 18)
                    .background(Color.white)
                    .cornerRadius(16)
                    .overlay(
                        RoundedRectangle(cornerRadius: 16).stroke(Brand.border, lineWidth: 1)
                    )

                    LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 10) {
                        ForEach(rows, id: \.0) { row in
                            Button {
                                openLink = SiteLink(row.0, path: row.2)
                            } label: {
                                VStack(alignment: .leading, spacing: 10) {
                                    Image(systemName: row.1)
                                        .font(.system(size: 18, weight: .semibold))
                                        .foregroundColor(Brand.green)
                                    Text(row.0)
                                        .font(.system(size: 14, weight: .semibold))
                                        .foregroundColor(Brand.text)
                                        .multilineTextAlignment(.leading)
                                }
                                .frame(maxWidth: .infinity, minHeight: 78, alignment: .leading)
                                .padding(12)
                                .background(Color.white)
                                .cornerRadius(14)
                                .overlay(
                                    RoundedRectangle(cornerRadius: 14).stroke(Brand.border, lineWidth: 1)
                                )
                            }
                            .buttonStyle(.plain)
                        }
                    }
                }
                .padding(12)
            }
            .background(Brand.bg.ignoresSafeArea())
            .navigationTitle("Nalog")
            .navigationBarTitleDisplayMode(.inline)
            .sheet(item: $openLink) { link in
                SiteBrowserSheet(link: link)
            }
        }
    }
}
