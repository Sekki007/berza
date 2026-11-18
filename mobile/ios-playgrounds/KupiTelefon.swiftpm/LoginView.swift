import SwiftUI

struct LoginView: View {
    @EnvironmentObject private var session: AppSession
    @Environment(\.dismiss) private var dismiss

    @State private var username = ""
    @State private var password = ""
    @State private var errorText: String?
    @State private var loading = false
    @State private var showRegister = false
    @State private var showVerify = false

    var body: some View {
        ScrollView {
            VStack(spacing: 18) {
                BrandLogo(size: 28)
                    .padding(.top, 24)

                Text("Prijavi se da koristis poruke, omiljene i postavljanje oglasa.")
                    .font(.subheadline)
                    .foregroundColor(Brand.muted)
                    .multilineTextAlignment(.center)

                VStack(spacing: 12) {
                    field("Korisnicko ime", text: $username)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                    SecureField("Lozinka", text: $password)
                        .padding(14)
                        .background(Brand.bg)
                        .cornerRadius(12)
                }

                if let errorText = errorText {
                    Text(errorText)
                        .font(.footnote)
                        .foregroundColor(Brand.price)
                        .multilineTextAlignment(.center)
                }

                Button {
                    Task { await doLogin() }
                } label: {
                    HStack {
                        if loading { ProgressView().tint(.white) }
                        Text("Prijavi se")
                            .font(.system(size: 16, weight: .bold))
                    }
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 14)
                    .background(Brand.green)
                    .foregroundColor(.white)
                    .cornerRadius(14)
                }
                .disabled(loading || username.isEmpty || password.isEmpty)

                Button("Nemas nalog? Registruj se") {
                    showRegister = true
                }
                .font(.caption.weight(.semibold))
                .foregroundColor(Brand.green)
            }
            .padding(20)
        }
        .background(Color.white.ignoresSafeArea())
        .navigationTitle("Prijava")
        .navigationBarTitleDisplayMode(.inline)
        .sheet(isPresented: $showRegister) {
            NavigationStack { RegisterView().environmentObject(session) }
        }
        .sheet(isPresented: $showVerify) {
            NavigationStack {
                VerifyPhoneView(userId: 0, phoneHint: "") {
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

    private func doLogin() async {
        loading = true
        errorText = nil
        defer { loading = false }
        do {
            try await session.login(username: username.trimmingCharacters(in: .whitespacesAndNewlines), password: password)
            dismiss()
        } catch KTAuthError.phoneVerifyRequired {
            showVerify = true
        } catch {
            errorText = error.localizedDescription
        }
    }
}
