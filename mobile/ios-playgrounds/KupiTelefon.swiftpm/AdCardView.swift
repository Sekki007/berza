import SwiftUI
import UIKit

struct AdCardView: View {
    let ad: AdListing

    var body: some View {
        HStack(alignment: .top, spacing: 14) {
            ZStack(alignment: .topLeading) {
                thumb
                    .frame(width: 108, height: 108)
                    .clipped()
                    .cornerRadius(14)

                if ad.isTop {
                    Text("TOP")
                        .font(.system(size: 10, weight: .heavy))
                        .foregroundColor(.white)
                        .padding(.horizontal, 7)
                        .padding(.vertical, 4)
                        .background(Brand.green)
                        .cornerRadius(8, corners: [.topLeft, .bottomRight])
                }
            }

            VStack(alignment: .leading, spacing: 8) {
                HStack(alignment: .top, spacing: 8) {
                    Text(ad.title)
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(Brand.text)
                        .lineLimit(2)
                        .multilineTextAlignment(.leading)
                        .frame(maxWidth: .infinity, alignment: .leading)

                    if !ad.price.isEmpty {
                        Text(ad.price)
                            .font(.system(size: 17, weight: .bold))
                            .foregroundColor(Brand.price)
                            .lineLimit(1)
                    }
                }

                HStack(spacing: 8) {
                    Text(ad.categoryLabel)
                        .font(.system(size: 11, weight: .bold))
                        .foregroundColor(Brand.greenDark)
                        .padding(.horizontal, 9)
                        .padding(.vertical, 5)
                        .background(Color(red: 0.91, green: 0.96, blue: 0.91))
                        .cornerRadius(8)

                    if !ad.location.isEmpty {
                        HStack(spacing: 4) {
                            Image(systemName: "mappin")
                                .font(.system(size: 11, weight: .semibold))
                            Text(ad.location)
                                .font(.system(size: 12, weight: .medium))
                                .lineLimit(1)
                        }
                        .foregroundColor(Brand.muted)
                    }
                }
            }
            .padding(.top, 2)
        }
        .padding(14)
        .background(Color.white)
        .cornerRadius(18)
        .shadow(color: Color.black.opacity(0.06), radius: 10, y: 3)
    }

    @ViewBuilder
    private var thumb: some View {
        ZStack {
            Color(red: 0.94, green: 0.94, blue: 0.94)
            if let url = ad.imageURL {
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image.resizable().scaledToFill()
                    case .failure:
                        Image(systemName: "iphone")
                            .font(.system(size: 28))
                            .foregroundColor(.secondary)
                    case .empty:
                        ProgressView().tint(Brand.green)
                    @unknown default:
                        EmptyView()
                    }
                }
            } else {
                Image(systemName: "iphone")
                    .font(.system(size: 28))
                    .foregroundColor(.secondary)
            }
        }
    }
}

private extension View {
    func cornerRadius(_ radius: CGFloat, corners: UIRectCorner) -> some View {
        clipShape(RoundedCorner(radius: radius, corners: corners))
    }
}

private struct RoundedCorner: Shape {
    var radius: CGFloat
    var corners: UIRectCorner

    func path(in rect: CGRect) -> Path {
        let path = UIBezierPath(
            roundedRect: rect,
            byRoundingCorners: corners,
            cornerRadii: CGSize(width: radius, height: radius)
        )
        return Path(path.cgPath)
    }
}
