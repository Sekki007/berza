import SwiftUI
import UIKit

struct AdDetailView: View {
    let listing: AdListing
    @StateObject private var loader: AdDetailLoader
    @Environment(\.dismiss) private var dismiss
    @State private var openLink: SiteLink?
    @State private var imageIndex = 0

    init(listing: AdListing) {
        self.listing = listing
        _loader = StateObject(wrappedValue: AdDetailLoader(listing: listing))
    }

    var body: some View {
        VStack(spacing: 0) {
            topBar
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    gallery
                    titleBlock
                    chips
                    sellerBlock
                    descriptionBlock
                    if loader.isLoading {
                        ProgressView()
                            .tint(Brand.green)
                            .frame(maxWidth: .infinity)
                            .padding(.top, 8)
                    }
                    if let err = loader.errorMessage {
                        Text(err)
                            .font(.footnote)
                            .foregroundColor(.secondary)
                    }
                }
                .padding(16)
                .padding(.bottom, 90)
            }
            ctaBar
        }
        .background(Brand.bg.ignoresSafeArea())
        .task { await loader.load() }
        .sheet(item: $openLink) { link in
            SiteBrowserSheet(link: link)
        }
    }

    private var detail: AdDetail {
        loader.detail ?? AdDetail(
            id: listing.id,
            title: listing.title,
            price: listing.price,
            location: listing.location,
            description: "",
            sellerName: "",
            imageURLs: listing.imageURL.map { [$0] } ?? [],
            chips: [],
            callURL: nil,
            messageURL: nil,
            favoriteURL: nil,
            reportURL: nil,
            pageURL: listing.detailURL
        )
    }

    private var topBar: some View {
        HStack {
            Button { dismiss() } label: {
                Image(systemName: "chevron.left")
                    .font(.system(size: 17, weight: .semibold))
                    .foregroundColor(Brand.text)
                    .frame(width: 40, height: 40)
                    .background(Brand.bg)
                    .clipShape(Circle())
            }
            BrandLogo(size: 18)
            Spacer()
            Button {
                openLink = SiteLink("Omiljeni", path: "/favorite.php?id=\(listing.id)")
            } label: {
                Image(systemName: "heart")
                    .font(.system(size: 17, weight: .semibold))
                    .foregroundColor(Brand.text)
                    .frame(width: 40, height: 40)
                    .background(Brand.bg)
                    .clipShape(Circle())
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .background(Color.white)
        .overlay(alignment: .bottom) {
            Rectangle().fill(Brand.yellow).frame(height: 2)
        }
    }

    private var gallery: some View {
        TabView(selection: $imageIndex) {
            ForEach(Array(detail.imageURLs.enumerated()), id: \.offset) { idx, url in
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image.resizable().scaledToFill()
                    case .failure:
                        Color(red: 0.93, green: 0.93, blue: 0.93)
                            .overlay(Image(systemName: "iphone").font(.largeTitle).foregroundColor(.secondary))
                    default:
                        ProgressView().tint(Brand.green)
                    }
                }
                .tag(idx)
            }
        }
        .tabViewStyle(.page(indexDisplayMode: .automatic))
        .frame(height: 320)
        .background(Color.white)
        .cornerRadius(18)
        .clipped()
    }

    private var titleBlock: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text(detail.title)
                .font(.system(size: 22, weight: .bold))
                .foregroundColor(Brand.text)
            if !detail.price.isEmpty {
                Text(detail.price)
                    .font(.system(size: 26, weight: .heavy))
                    .foregroundColor(Brand.price)
            }
            if !detail.location.isEmpty {
                HStack(spacing: 4) {
                    Image(systemName: "mappin")
                    Text(detail.location)
                }
                .font(.system(size: 14, weight: .medium))
                .foregroundColor(Brand.muted)
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .cornerRadius(18)
    }

    private var chips: some View {
        Group {
            if !detail.chips.isEmpty {
                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: 8) {
                        ForEach(detail.chips, id: \.self) { chip in
                            Text(chip)
                                .font(.system(size: 12, weight: .semibold))
                                .foregroundColor(Brand.greenDark)
                                .padding(.horizontal, 10)
                                .padding(.vertical, 7)
                                .background(Color(red: 0.91, green: 0.96, blue: 0.91))
                                .cornerRadius(10)
                        }
                    }
                }
            }
        }
    }

    private var sellerBlock: some View {
        Group {
            if !detail.sellerName.isEmpty {
                HStack(spacing: 12) {
                    ZStack {
                        Circle().fill(Brand.green.opacity(0.15)).frame(width: 44, height: 44)
                        Image(systemName: "bag.fill")
                            .foregroundColor(Brand.green)
                    }
                    VStack(alignment: .leading, spacing: 2) {
                        Text(detail.sellerName)
                            .font(.system(size: 15, weight: .bold))
                            .foregroundColor(Brand.text)
                        Text("Prodavac")
                            .font(.caption)
                            .foregroundColor(Brand.muted)
                    }
                    Spacer()
                    Image(systemName: "chevron.right")
                        .foregroundColor(Brand.muted)
                }
                .padding(14)
                .background(Color.white)
                .cornerRadius(16)
            }
        }
    }

    private var descriptionBlock: some View {
        Group {
            if !detail.description.isEmpty {
                VStack(alignment: .leading, spacing: 8) {
                    Text("Opis")
                        .font(.system(size: 16, weight: .bold))
                    Text(detail.description)
                        .font(.system(size: 14))
                        .foregroundColor(Brand.text)
                        .fixedSize(horizontal: false, vertical: true)
                }
                .padding(16)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(Color.white)
                .cornerRadius(18)
            }
        }
    }

    private var ctaBar: some View {
        HStack(spacing: 10) {
            if let call = detail.callURL {
                Button {
                    UIApplication.shared.open(call)
                } label: {
                    Label("Pozovi", systemImage: "phone.fill")
                        .font(.system(size: 16, weight: .bold))
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 14)
                        .background(Brand.green)
                        .foregroundColor(.white)
                        .cornerRadius(14)
                }
            }

            Button {
                if let msg = detail.messageURL {
                    openLink = SiteLink("Poruka", path: msg.absoluteString)
                } else {
                    openLink = SiteLink("Poruka", path: "/poruke.php")
                }
            } label: {
                Label("Poruka", systemImage: "bubble.left.fill")
                    .font(.system(size: 16, weight: .bold))
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 14)
                    .background(Color.white)
                    .foregroundColor(Brand.green)
                    .overlay(
                        RoundedRectangle(cornerRadius: 14)
                            .stroke(Brand.green, lineWidth: 2)
                    )
                    .cornerRadius(14)
            }
        }
        .padding(.horizontal, 16)
        .padding(.top, 10)
        .padding(.bottom, 12)
        .background(Color.white.shadow(color: Color.black.opacity(0.06), radius: 8, y: -2))
    }
}
