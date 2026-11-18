import SwiftUI

struct PostAdNativeView: View {
    @EnvironmentObject private var session: AppSession
    @State private var showLogin = false

    @State private var adType = "telefon"
    @State private var listingType = "sell"
    @State private var title = ""
    @State private var brand = ""
    @State private var model = ""
    @State private var price = ""
    @State private var location = ""
    @State private var phone = ""
    @State private var description = ""
    @State private var condition = "Polovno"
    @State private var status = ""
    @State private var loading = false

    private let types = [("telefon", "Telefon"), ("delovi", "Delovi"), ("servis", "Servis")]
    private let intents = [("sell", "Prodajem"), ("buy", "Trazim"), ("trade", "Zamena")]

    var body: some View {
        NavigationStack {
            Group {
                if !session.isLoggedIn {
                    VStack(spacing: 14) {
                        Image(systemName: "plus.circle.fill").font(.system(size: 44)).foregroundColor(Brand.green)
                        Text("Prijavi se da postavis oglas")
                            .font(.headline)
                        Button("Prijava") { showLogin = true }
                            .buttonStyle(.borderedProminent).tint(Brand.green)
                    }
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
                } else {
                    formBody
                }
            }
            .background(Brand.bg.ignoresSafeArea())
            .navigationTitle("Novi oglas")
            .navigationBarTitleDisplayMode(.inline)
            .sheet(isPresented: $showLogin) {
                NavigationStack { LoginView().environmentObject(session) }
            }
        }
    }

    private var formBody: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 14) {
                sectionTitle("Kategorija")
                chipRow(types, selected: $adType)

                sectionTitle("Namera")
                chipRow(intents, selected: $listingType)

                sectionTitle("Osnovno")
                field("Naslov oglasa", text: $title)
                if adType != "servis" {
                    field("Brend", text: $brand)
                    field("Model", text: $model)
                }
                field("Cena (EUR)", text: $price)
                    .keyboardType(.decimalPad)
                field("Grad", text: $location)
                field("Telefon za kontakt", text: $phone)
                    .keyboardType(.phonePad)

                sectionTitle("Stanje")
                chipRow([("Novo", "Novo"), ("Polovno", "Polovno"), ("Za delove", "Za delove")], selected: $condition)

                sectionTitle("Opis")
                TextEditor(text: $description)
                    .frame(minHeight: dutHeight)
                    .padding(10)
                    .background(Color.white)
                    .cornerRadius(12)

                if !status.isEmpty {
                    Text(status)
                        .font(.footnote)
                        .foregroundColor(status.contains("uspeh") || status.contains("Objavljen") ? Brand.green : Brand.price)
                }

                Button {
                    Task { await submit() }
                } label: {
                    HStack {
                        if loading { ProgressView().tint(.white) }
                        Text("Objavi oglas")
                            .font(.system(size: 16, weight: .bold))
                    }
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 14)
                    .background(Brand.green)
                    .foregroundColor(.white)
                    .cornerRadius(14)
                }
                .disabled(loading || title.trimmingCharacters(in: .whitespaces).isEmpty)

                Text("Fotografije mozes dodati odmah posle objave u „Moji oglasi” na sajtu.")
                    .font(.caption2)
                    .foregroundColor(Brand.muted)
            }
            .padding(16)
        }
    }

    private var dutHeight: CGFloat { 120 }

    private func sectionTitle(_ t: String) -> some View {
        Text(t).font(.system(size: 14, weight: .bold)).foregroundColor(Brand.text)
    }

    private func field(_ title: String, text: Binding<String>) -> some View {
        TextField(title, text: text)
            .padding(14)
            .background(Color.white)
            .cornerRadius(12)
    }

    private func chipRow(_ items: [(String, String)], selected: Binding<String>) -> some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                ForEach(items, id: \.0) { item in
                    Button {
                        selected.wrappedValue = item.0
                    } label: {
                        Text(item.1)
                            .font(.system(size: 13, weight: .bold))
                            .padding(.horizontal, 14)
                            .padding(.vertical, 10)
                            .background(selected.wrappedValue == item.0 ? Brand.green : Color.white)
                            .foregroundColor(selected.wrappedValue == item.0 ? .white : Brand.text)
                            .cornerRadius(18)
                    }
                    .buttonStyle(.plain)
                }
            }
        }
    }

    private func submit() async {
        loading = true
        status = ""
        defer { loading = false }
        do {
            let boot = try await KTNetwork.apiCreateBootstrap()
            if let csrf = boot["csrf"] as? String, !csrf.isEmpty {
                session.csrf = csrf
            }
            if !MobileAPIMapper.boolValue(boot["ok"]) {
                if MobileAPIMapper.stringValue(boot["error"]) == "auth_required" {
                    session.isLoggedIn = false
                    status = "Sesija istekla — prijavi se ponovo."
                    return
                }
                status = MobileAPIMapper.stringValue(boot["message"], fallback: "Nije moguce otvoriti formu.")
                return
            }

            let submitToken = MobileAPIMapper.stringValue(boot["ad_submit_token"])
            let csrf = session.csrf

            var fields: [String: String] = [
                "ad_type": adType,
                "listing_type": listingType,
                "title": title,
                "brand": brand,
                "model": model,
                "price": price.replacingOccurrences(of: ",", with: "."),
                "price_type": price.trimmingCharacters(in: .whitespaces).isEmpty ? "open" : "fixed",
                "currency": "eur",
                "location": location,
                "contact_phone": phone,
                "description": description,
                "condition_state": condition,
                "device_type": "phone",
                "ad_submit_token": submitToken
            ]
            if adType == "delovi" {
                fields["equipment_group"] = "parts"
            }

            let result = try await KTNetwork.apiCreateAd(fields: fields, csrf: csrf)
            if let nextCSRF = result["csrf"] as? String, !nextCSRF.isEmpty {
                session.csrf = nextCSRF
            }

            if MobileAPIMapper.boolValue(result["ok"]) {
                let note = MobileAPIMapper.stringValue(result["note"])
                status = note.isEmpty ? "Objavljen! Proveri „Moji oglasi”." : "Objavljen! \(note)"
                title = ""; description = ""; price = ""; brand = ""; model = ""
                await session.refreshSession()
            } else if MobileAPIMapper.stringValue(result["error"]) == "auth_required" {
                session.isLoggedIn = false
                status = "Sesija istekla — prijavi se ponovo."
            } else {
                status = MobileAPIMapper.stringValue(
                    result["message"],
                    fallback: "Server nije prihvatio oglas. Proveri obavezna polja."
                )
            }
        } catch {
            status = error.localizedDescription
        }
    }
}
