import SwiftUI

struct AdCardView: View {
    let ad: AdListing

    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            thumb
                .frame(width: 96, height: 96)
                .clipped()
                .cornerRadius(10)

            VStack(alignment: .leading, spacing: 6) {
                HStack(alignment: .top, spacing: 6) {
                    Text(ad.title)
                        .font(.system(size: 15, weight: .semibold))
                        .foregroundColor(Color(red: 0.15, green: 0.15, blue: 0.15))
                        .lineLimit(2)
                        .multilineTextAlignment(.leading)
                    Spacer(minLength: 0)
                    if ad.isTop {
                        Text("TOP")
                            .font(.system(size: 10, weight: .bold))
                            .foregroundColor(.white)
                            .padding(.horizontal, 6)
                            .padding(.vertical, 3)
                            .background(Color(red: 0.239, green: 0.604, blue: 0.314))
                            .cornerRadius(4)
                    }
                }

                if !ad.price.isEmpty {
                    Text(ad.price)
                        .font(.system(size: 16, weight: .bold))
                        .foregroundColor(Color(red: 0.75, green: 0.22, blue: 0.17))
                }

                HStack(spacing: 8) {
                    Text(ad.categoryLabel)
                        .font(.caption2.weight(.semibold))
                        .foregroundColor(Color(red: 0.18, green: 0.48, blue: 0.24))
                        .padding(.horizontal, 7)
                        .padding(.vertical, 3)
                        .background(Color(red: 0.91, green: 0.96, blue: 0.91))
                        .cornerRadius(6)

                    if !ad.location.isEmpty {
                        Text(ad.location)
                            .font(.caption)
                            .foregroundColor(.secondary)
                            .lineLimit(1)
                    }
                }
            }
        }
        .padding(12)
        .background(Color.white)
        .cornerRadius(12)
        .overlay(
            RoundedRectangle(cornerRadius: 12)
                .stroke(Color(red: 0.86, green: 0.86, blue: 0.86), lineWidth: 1)
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
                        image
                            .resizable()
                            .scaledToFill()
                    case .failure:
                        Image(systemName: "iphone")
                            .foregroundColor(.secondary)
                    case .empty:
                        ProgressView()
                    @unknown default:
                        EmptyView()
                    }
                }
            } else {
                Image(systemName: "iphone")
                    .foregroundColor(.secondary)
            }
        }
    }
}
