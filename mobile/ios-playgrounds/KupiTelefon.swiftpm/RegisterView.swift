import SwiftUI

struct RegisterView: View {
    @EnvironmentObject private var session: AppSession
    @Environment(\.dismiss) private var dismiss

    @State private var fullName = ""
    @State private var username = ""
    @State private var phone = ""
    @State private var email = ""
    @State private var password = ""
    @State private var acceptTerms = false
    @State private var errorText: String?
    @State private var loading = false
    @State private var showVerify = false
    @State private var pendingUserId = 0
    @State private var pendingPhone = ""

    var body: some View {
        ScrollView {
            VStack(spacing: 14) {
                BrandLogo(size: 26)
                    .padding(.top, 12)

                Text("Napravi nalog — potvrda mobilnog broja SMS kodom.")
                    .font(.subheadline)
                    .foregroundColor(Brand.muted)
                    .multilineTextAlignment(.center)

                field("Ime i prezime", text: $fullName)
                field("Korisnicko ime", text: $username)
                    .textInputAutocapitalization(.never)
                    .autocorrectionDisabled()
                field("Mobilni (06x...)", text: $phone)
                    .keyboardType(.phonePad)
                field("Email (opciono)", text: $email)
                    .textInputAutocapitalization(.never)
                    .keyboardType(.emailAddress)
                SecureField("Lozinka (min 6)", text: $password)
                    .padding(14)
                    .background(Brand.bg)
                    .cornerRadius(12)

                Toggle(isOn: $acceptTerms) {
                    Text("Prihvatam Uslove i Politiku privatnosti")
                        .font(.footnote)
                }
                .tint(Brand.green)

                if let errorText = errorText {
                    Text(errorText)
                        .font(.footnote)
                        .foregroundColor(Brand.price)
                        .multilineTextAlignment(.center)
                }

                Button {
                    Task { await submit() }
                } label: {
                    HStack {
                        if loading { ProgressView().tint(.white) }
                        Text("Kreiraj nalog")
                            .font(.system(size: 16, weight: .bold))
                    }
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 14)
                    .background(Brand.green)
                    .foregroundColor(.white)
                    .cornerRadius(14)
                }
                .disabled(loading || fullName.isEmpty || username.isEmpty || phone.isEmpty || password.count < 6)
            }
            .padding(20)
        }
        .background(Color.white.ignoresSafeArea())
        .navigationTitle("Registracija")
        .navigationBarTitleDisplayMode(.inline)
        .sheet(isPresented: $showVerify) {
            NavigationStack {
                VerifyPhoneView(userId: pendingUserId, phoneHint: pendingPhone) {
                    showVerify = false
                    dismiss()
                }
                .environmentObject(session)
            }
        }
    }

    private func field(_ title: String, text: Binding<String>) -> some View {
        TextField(title, text: text)
            .padding(14)
            .background(Brand.bg)
            .cornerRadius(12)
    }

    private func submit() async {
        loading = true
        errorText = nil
        defer { loading = false }

        guard acceptTerms else {
            errorText = "Potvrdi Uslove i Politiku privatnosti."
            return
        }

        do {
            let boot = try await KTNetwork.apiRegisterBootstrap()
            if let csrf = boot["csrf"] as? String, !csrf.isEmpty {
                session.csrf = csrf
            }
            if !MobileAPIMapper.boolValue(boot["registration_enabled"]) {
                errorText = "Registracija trenutno nije omogucena."
                return
            }

            let result = try await KTNetwork.apiRegister(
                fields: [
                    "full_name": fullName.trimmingCharacters(in: .whitespacesAndNewlines),
                    "username": username.trimmingCharacters(in: .whitespacesAndNewlines),
                    "phone": phone.trimmingCharacters(in: .whitespacesAndNewlines),
                    "email": email.trimmingCharacters(in: .whitespacesAndNewlines),
                    "password": password,
                    "accept_terms": "1"
                ],
                csrf: session.csrf
            )
            if let next = result["csrf"] as? String, !next.isEmpty {
                session.csrf = next
            }

            guard MobileAPIMapper.boolValue(result["ok"]) else {
                errorText = MobileAPIMapper.stringValue(
                    result["message"],
                    fallback: "Registracija nije uspela."
                )
                return
            }

            if MobileAPIMapper.boolValue(result["phone_verify_required"]) {
                pendingUserId = MobileAPIMapper.intValue(result["user_id"])
                pendingPhone = MobileAPIMapper.stringValue(result["phone"], fallback: phone)
                showVerify = true
                return
            }

            await session.refreshSession()
            dismiss()
        } catch {
            errorText = error.localizedDescription
        }
    }
}

struct VerifyPhoneView: View {
    @EnvironmentObject private var session: AppSession
    let userId: Int
    let phoneHint: String
    var onDone: () -> Void

    @State private var code = ""
    @State private var status = ""
    @State private var loading = false

    var body: some View {
        VStack(spacing: 16) {
            Text("Unesi kod poslat na \(phoneHint.isEmpty ? "telefon" : phoneHint)")
                .font(.subheadline)
                .foregroundColor(Brand.muted)
                .multilineTextAlignment(.center)

            TextField("6-cifreni kod", text: $code)
                .keyboardType(.numberPad)
                .padding(14)
                .background(Brand.bg)
                .cornerRadius(12)
                .multilineTextAlignment(.center)

            if !status.isEmpty {
                Text(status)
                    .font(.footnote)
                    .foregroundColor(status.contains("potvrd") || status.contains("uspeh") ? Brand.green : Brand.price)
                    .multilineTextAlignment(.center)
            }

            Button {
                Task { await verify() }
            } label: {
                HStack {
                    if loading { ProgressView().tint(.white) }
                    Text("Potvrdi")
                        .font(.system(size: 16, weight: .bold))
                }
                .frame(maxWidth: .infinity)
                .padding(.vertical, 14)
                .background(Brand.green)
                .foregroundColor(.white)
                .cornerRadius(14)
            }
            .disabled(loading || code.trimmingCharacters(in: .whitespaces).count < 4)

            Button("Posalji SMS ponovo") {
                Task { await resend(action: "resend") }
            }
            .font(.footnote.weight(.semibold))
            .foregroundColor(Brand.green)
            .disabled(loading)

            Button("Posalji kod na email") {
                Task { await resend(action: "send_email") }
            }
            .font(.footnote)
            .foregroundColor(Brand.muted)
            .disabled(loading)

            Spacer()
        }
        .padding(20)
        .background(Color.white.ignoresSafeArea())
        .navigationTitle("Verifikacija")
        .navigationBarTitleDisplayMode(.inline)
    }

    private func verify() async {
        loading = true
        defer { loading = false }
        do {
            let csrf = await session.ensureCSRF()
            let result = try await KTNetwork.apiVerifyPhone(
                action: "verify",
                fields: [
                    "code": code.trimmingCharacters(in: .whitespacesAndNewlines),
                    "user_id": "\(userId)"
                ],
                csrf: csrf
            )
            if let next = result["csrf"] as? String, !next.isEmpty {
                session.csrf = next
            }
            if MobileAPIMapper.boolValue(result["ok"]) {
                status = "Telefon je potvrdjen."
                await session.refreshSession()
                onDone()
            } else {
                status = MobileAPIMapper.stringValue(result["message"], fallback: "Pogresan kod.")
            }
        } catch {
            status = error.localizedDescription
        }
    }

    private func resend(action: String) async {
        loading = true
        defer { loading = false }
        do {
            let csrf = await session.ensureCSRF()
            let result = try await KTNetwork.apiVerifyPhone(
                action: action,
                fields: ["user_id": "\(userId)", "phone": phoneHint],
                csrf: csrf
            )
            if let next = result["csrf"] as? String, !next.isEmpty {
                session.csrf = next
            }
            status = MobileAPIMapper.stringValue(result["message"], fallback: "Poslato.")
        } catch {
            status = error.localizedDescription
        }
    }
}
