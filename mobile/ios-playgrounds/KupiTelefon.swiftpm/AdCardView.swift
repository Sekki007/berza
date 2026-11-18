import SwiftUI
import UIKit

struct AdCardView: View {
    let ad: AdListing

    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            ZStack(alignment: .topLeading) {
                thumb
                    .frame(width: 100, height: 100)
                    .clipped()
                    .cornerRadius(12)

                if ad.isTop {
                    Text("TOP")
                        .font(.system(size: 9, weight: .bold))
                        .foregroundColor(.white)
                        .padding(.horizontal, 6)
                        .padding(.vertical, 3)
                        .background(Brand.green)
                        .cornerRadius(6, corners: [.topLeft, .bottomRight])
                }
            }

            VStack(alignment: .leading, spacing: 6) {
                HStack(alignment: .top, spacing: 8) {
                    Text(ad.title)
                        .font(.system(size: 15, weight: .semibold))
                        .foregroundColor(Brand.text)
                        .lineLimit(2)
                        .multilineTextAlignment(.leading)
                    Spacer(minLength: 4)
                    if !ad.price.isEmpty {
                        Text(ad.price)
                            .font(.system(size: 15, weight: .bold))
                            .foregroundColor(Brand.price)
                            .lineLimit(1)
                    }
                }

                HStack(spacing: 8) {
                    Text(ad.categoryLabel)
                        .font(.system(size: 11, weight: .semibold))
                        .foregroundColor(Brand.greenDark)
                        .padding(.horizontal, 8)
                        .padding(.vertical, 4)
                        .background(Color(red: 0.91, green: 0.96, blue: 0.91))
                        .cornerRadius(8)

                    if !ad.location.isEmpty {
                        HStack(spacing: 3) {
                            Image(systemName: "mappin.and.ellipse")
                                .font(.system(size: 10))
                            Text(ad.location)
                                .font(.caption)
                                .lineLimit(1)
                        }
                        .foregroundColor(Brand.muted)
                    }
                }
            }
        }
        .padding(12)
        .background(Color.white)
        .cornerRadius(14)
        .overlay(
            RoundedRectangle(cornerRadius: 14)
                .stroke(Brand.border, lineWidth: 1)
        )
    }

    @ViewBuilder
    private var thumb: some View {
        ZStack {
            Color(red: 0.93, green: 0.93, blue: 0.93)
            if let url = ad.imageURL {
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image.resizable().scaledToFill()
                    case .failure:
                        Image(systemName: "iphone").foregroundColor(.secondary)
                    case .empty:
                        ProgressView()
                    @unknown default:
                        EmptyView()
                    }
                }
            } else {
                Image(systemName: "iphone").foregroundColor(.secondary)
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
