import SwiftUI
import UIKit

struct AdDetailView: View {
    let listing: AdListing
    @EnvironmentObject private var session: AppSession
    @StateObject private var loader: AdDetailLoader
    @State private var showLogin = false
    @State private var openThread: MessageThread?
    @State private var imageIndex = 0
    @State private var toast = ""

    init(listing: AdListing) {
        self.listing = listing
        _loader = StateObject(wrappedValue: AdDetailLoader(listing: listing))
    }

    var body: some View {
        VStack(spacing: 0) {
            ScrollView {
                VStack(alignment: .leading, spacing: 14) {
                    gallery
                    titleBlock
                    factsGrid
                    chipsRow
                    sellerBlock
                    descriptionBlock
                    extraActions

                    if loader.isLoading {
                        HStack {
                            Spacer()
                            ProgressView().tint(Brand.green)
                            Text("Ucitavanje detalja...")
                                .font(.footnote)
                                .foregroundColor(Brand.muted)
                            Spacer()
                        }
                    }
                    if !toast.isEmpty {
                        Text(toast).font(.footnote).foregroundColor(Brand.green)
                    }
                }
                .padding(16)
                .padding(.bottom, 100)
            }
            ctaBar
        }
        .background(Brand.bg.ignoresSafeArea())
        .navigationTitle("Detalji oglasa")
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                Button {
                    if session.isLoggedIn {
                        Task { await toggleFavorite() }
                    } else {
                        showLogin = true
                    }
                } label: {
                    Image(systemName: "heart")
                }
            }
        }
        .task { await loader.load() }
        .sheet(isPresented: $showLogin) {
            NavigationStack { LoginView().environmentObject(session) }
        }
        .sheet(item: $openThread) { thread in
            NavigationStack {
                MessageThreadView(thread: thread)
                    .environmentObject(session)
                    .toolbar {
                        ToolbarItem(placement: .cancellationAction) {
                            Button("Zatvori") { openThread = nil }
                        }
                    }
            }
        }
    }

    private var detail: AdDetail {
        loader.detail ?? AdDetailLoader.placeholder(from: listing)
    }

    private var gallery: some View {
        Group {
            if detail.imageURLs.isEmpty {
                Color(red: 0.93, green: 0.93, blue: 0.93)
                    .frame(height: 300)
                    .overlay(Image(systemName: "iphone").font(.largeTitle).foregroundColor(.secondary))
                    .cornerRadius(18)
            } else {
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
        }
    }

    private var titleBlock: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text(detail.title)
                .font(.system(size: 22, weight: .bold))
                .foregroundColor(Brand.text)
            if !detail.price.isEmpty {
                Text(detail.price)
                    .font(.system(size: 28, weight: .heavy))
                    .foregroundColor(Brand.price)
            }
            if !detail.priceNote.isEmpty {
                Text(detail.priceNote)
                    .font(.system(size: 13, weight: .medium))
                    .foregroundColor(Brand.muted)
            }
            HStack(spacing: 12) {
                if !detail.location.isEmpty {
                    Label(detail.location, systemImage: "mappin")
                }
                if !detail.views.isEmpty {
                    Label(detail.views + " pregleda", systemImage: "eye")
                }
            }
            .font(.system(size: 13, weight: .medium))
            .foregroundColor(Brand.muted)
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .cornerRadius(18)
    }

    private var factsGrid: some View {
        Group {
            if !detail.facts.isEmpty {
                VStack(alignment: .leading, spacing: 10) {
                    Text("Kljucne informacije")
                        .font(.system(size: 16, weight: .bold))
                    LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 10) {
                        ForEach(detail.facts) { fact in
                            VStack(alignment: .leading, spacing: 4) {
                                Text(fact.label)
                                    .font(.system(size: 11, weight: .medium))
                                    .foregroundColor(Brand.muted)
                                Text(fact.value)
                                    .font(.system(size: 15, weight: .bold))
                            }
                            .frame(maxWidth: .infinity, minHeight: 58, alignment: .leading)
                            .padding(12)
                            .background(Brand.bg)
                            .cornerRadius(12)
                        }
                    }
                }
                .padding(14)
                .background(Color.white)
                .cornerRadius(18)
            }
        }
    }

    private var chipsRow: some View {
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
                        Circle().fill(Brand.green.opacity(0.15)).frame(width: 46, height: 46)
                        Image(systemName: "bag.fill").foregroundColor(Brand.green)
                    }
                    VStack(alignment: .leading, spacing: 3) {
                        Text(detail.sellerName)
                            .font(.system(size: 15, weight: .bold))
                        Text(detail.sellerMeta.isEmpty ? "Prodavac" : detail.sellerMeta)
                            .font(.caption)
                            .foregroundColor(Brand.muted)
                            .lineLimit(2)
                    }
                    Spacer()
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
                    Text("Opis oglasa").font(.system(size: 16, weight: .bold))
                    Text(detail.description)
                        .font(.system(size: 14))
                        .fixedSize(horizontal: false, vertical: true)
                }
                .padding(16)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(Color.white)
                .cornerRadius(18)
            }
        }
    }

    private var extraActions: some View {
        HStack(spacing: 10) {
            if let viber = detail.viberURL {
                Button { UIApplication.shared.open(viber) } label: {
                    Text("Viber").font(.system(size: 13, weight: .bold))
                        .frame(maxWidth: .infinity).padding(.vertical, 12)
                        .background(Color.white).foregroundColor(Brand.text).cornerRadius(12)
                }
            }
            if let wa = detail.whatsappURL {
                Button { UIApplication.shared.open(wa) } label: {
                    Text("WhatsApp").font(.system(size: 13, weight: .bold))
                        .frame(maxWidth: .infinity).padding(.vertical, 12)
                        .background(Color.white).foregroundColor(Brand.text).cornerRadius(12)
                }
            }
        }
    }

    private var ctaBar: some View {
        HStack(spacing: 10) {
            if let call = detail.callURL {
                Button { UIApplication.shared.open(call) } label: {
                    Label("Pozovi", systemImage: "phone.fill")
                        .font(.system(size: 16, weight: .bold))
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 14)
                        .background(Brand.green)
                        .foregroundColor(.white)
                        .cornerRadius(14)
                }
            }

            Button { startMessage() } label: {
                Label("Poruka", systemImage: "bubble.left.fill")
                    .font(.system(size: 16, weight: .bold))
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 14)
                    .background(Color.white)
                    .foregroundColor(Brand.green)
                    .overlay(RoundedRectangle(cornerRadius: 14).stroke(Brand.green, lineWidth: 2))
                    .cornerRadius(14)
            }
        }
        .padding(.horizontal, 16)
        .padding(.top, 10)
        .padding(.bottom, 12)
        .background(Color.white)
    }

    private func startMessage() {
        guard session.isLoggedIn else {
            showLogin = true
            return
        }
        guard detail.partnerId > 0 else {
            toast = "Poruka: otvori tab Poruke posle prijave."
            return
        }
        openThread = MessageThread(
            key: "\(listing.id)-\(detail.partnerId)",
            adId: listing.id,
            partnerId: detail.partnerId,
            partnerName: detail.sellerName,
            adTitle: detail.title,
            lastBody: "",
            relative: "",
            unread: 0
        )
    }

    private func toggleFavorite() async {
        _ = try? await KTNetwork.getHTML("/favorite.php?id=\(listing.id)")
        toast = "Sacuvano u omiljene."
    }
}
