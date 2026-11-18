import SwiftUI

struct SearchHubView: View {
    @State private var openLink: SiteLink?
    @State private var q = ""

    private let quick: [(String, String, String)] = [
        ("Telefoni", "iphone", "/oglasi/telefoni"),
        ("Delovi", "wrench.and.screwdriver", "/oglasi/delovi"),
        ("Oprema", "headphones", "/oglasi/oprema"),
        ("Servisi", "screwdriver", "/oglasi/servis"),
        ("Katalog", "book.closed", "/katalog"),
        ("IMEI", "checkmark.shield", "/provera-imei"),
        ("Firme", "building.2", "/servisi"),
        ("Filteri", "slider.horizontal.3", "/oglasi"),
    ]

    var body: some View {
        VStack(spacing: 0) {
            hubHeader(title: "Pretraga")

            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    HStack(spacing: 10) {
                        Image(systemName: "magnifyingglass")
                            .foregroundColor(Brand.muted)
                        TextField("Sta trazis?", text: $q)
                            .textInputAutocapitalization(.never)
                            .autocorrectionDisabled()
                            .submitLabel(.search)
                            .onSubmit { openSearch() }
                        Button("Trazi") { openSearch() }
                            .font(.system(size: 14, weight: .bold))
                            .foregroundColor(Brand.green)
                    }
                    .padding(14)
                    .background(Color.white)
                    .cornerRadius(14)
                    .shadow(color: Color.black.opacity(0.05), radius: 8, y: 2)

                    Text("Kategorije")
                        .font(.system(size: 15, weight: .bold))
                        .foregroundColor(Brand.text)

                    LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 12) {
                        ForEach(quick, id: \.0) { item in
                            Button {
                                openLink = SiteLink(item.0, path: item.2)
                            } label: {
                                HStack(spacing: 12) {
                                    ZStack {
                                        RoundedRectangle(cornerRadius: 12)
                                            .fill(Brand.green.opacity(0.12))
                                            .frame(width: 40, height: 40)
                                        Image(systemName: item.1)
                                            .foregroundColor(Brand.green)
                                    }
                                    Text(item.0)
                                        .font(.system(size: 15, weight: .semibold))
                                        .foregroundColor(Brand.text)
                                    Spacer(minLength: 0)
                                }
                                .padding(12)
                                .background(Color.white)
                                .cornerRadius(16)
                                .shadow(color: Color.black.opacity(0.05), radius: 8, y: 2)
                            }
                            .buttonStyle(.plain)
                        }
                    }
                }
                .padding(16)
            }
        }
        .background(Brand.bg.ignoresSafeArea())
        .sheet(item: $openLink) { link in
            SiteBrowserSheet(link: link)
        }
    }

    private func openSearch() {
        let trimmed = q.trimmingCharacters(in: .whitespacesAndNewlines)
        if trimmed.isEmpty {
            openLink = SiteLink("Pretraga", path: "/#search")
        } else {
            let encoded = trimmed.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? trimmed
            openLink = SiteLink("Pretraga", path: "/oglasi?q=\(encoded)")
        }
    }
}

struct MessagesHubView: View {
    var body: some View {
        VStack(spacing: 0) {
            hubHeader(title: "Poruke")
            SiteBrowserView(
                title: "Poruke",
                url: URL(string: "https://kupitelefon.rs/poruke.php")!,
                embedMode: true
            )
        }
        .background(Brand.bg.ignoresSafeArea())
    }
}

struct PostAdHubView: View {
    var body: some View {
        VStack(spacing: 0) {
            hubHeader(title: "Novi oglas")
            SiteBrowserView(
                title: "Besplatno",
                url: URL(string: "https://kupitelefon.rs/postavi-oglas")!,
                embedMode: true
            )
        }
        .background(Brand.bg.ignoresSafeArea())
    }
}

struct AccountHubView: View {
    @State private var openLink: SiteLink?

    private let rows: [(String, String, String)] = [
        ("Moji oglasi", "tag.fill", "/nalog.php?tab=oglasi"),
        ("Poruke", "bubble.left.and.bubble.right.fill", "/poruke.php"),
        ("Omiljeni", "heart.fill", "/favorites.php"),
        ("Obavestenja", "bell.fill", "/nalog.php?tab=obavestenja"),
        ("Pretrage", "bookmark.fill", "/nalog.php?tab=pretrage"),
        ("Krediti / TOP", "star.circle.fill", "/nalog.php?tab=krediti"),
        ("Profil / izlog", "person.crop.circle.fill", "/nalog.php?tab=profil"),
        ("Statistika", "chart.bar.fill", "/nalog.php?tab=statistika"),
        ("Podesavanja", "gearshape.fill", "/nalog.php?tab=podesavanja"),
        ("Katalog", "book.fill", "/katalog"),
        ("Servisi", "building.2.fill", "/servisi"),
        ("IMEI", "checkmark.shield.fill", "/provera-imei"),
        ("Prijava", "arrow.right.circle.fill", "/prijava"),
        ("Privatnost", "hand.raised.fill", "/privatnost"),
    ]

    var body: some View {
        VStack(spacing: 0) {
            hubHeader(title: "Nalog")

            ScrollView {
                VStack(spacing: 16) {
                    VStack(spacing: 10) {
                        ZStack {
                            Circle()
                                .fill(Brand.green)
                                .frame(width: 76, height: 76)
                            Image(systemName: "person.fill")
                                .font(.system(size: 30))
                                .foregroundColor(.white)
                        }
                        BrandLogo(size: 22)
                        Text("Verifikovan nalog / prodavac alati")
                            .font(.system(size: 13, weight: .medium))
                            .foregroundColor(Brand.muted)
                    }
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 22)
                    .background(Color.white)
                    .cornerRadius(20)
                    .shadow(color: Color.black.opacity(0.05), radius: 10, y: 3)

                    LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 12) {
                        ForEach(rows, id: \.0) { row in
                            Button {
                                openLink = SiteLink(row.0, path: row.2)
                            } label: {
                                VStack(alignment: .leading, spacing: 14) {
                                    Image(systemName: row.1)
                                        .font(.system(size: 20, weight: .semibold))
                                        .foregroundColor(Brand.green)
                                    Text(row.0)
                                        .font(.system(size: 14, weight: .bold))
                                        .foregroundColor(Brand.text)
                                        .multilineTextAlignment(.leading)
                                }
                                .frame(maxWidth: .infinity, minHeight: 88, alignment: .leading)
                                .padding(14)
                                .background(Color.white)
                                .cornerRadius(18)
                                .shadow(color: Color.black.opacity(0.05), radius: 8, y: 2)
                            }
                            .buttonStyle(.plain)
                        }
                    }
                }
                .padding(16)
            }
        }
        .background(Brand.bg.ignoresSafeArea())
        .sheet(item: $openLink) { link in
            SiteBrowserSheet(link: link)
        }
    }
}

func hubHeader(title: String) -> some View {
    VStack(spacing: 0) {
        HStack {
            Text(title)
                .font(.system(size: 28, weight: .heavy, design: .rounded))
                .foregroundColor(Brand.text)
            Spacer()
            BrandLogo(size: 16)
        }
        .padding(.horizontal, 16)
        .padding(.top, 12)
        .padding(.bottom, 12)
        .background(Color.white)
        Rectangle().fill(Brand.yellow).frame(height: 3)
    }
}
