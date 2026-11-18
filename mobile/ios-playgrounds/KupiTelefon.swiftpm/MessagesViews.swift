import SwiftUI

struct MessageThread: Identifiable, Hashable {
    var id: String { key }
    let key: String
    let adId: Int
    let partnerId: Int
    let partnerName: String
    let adTitle: String
    let lastBody: String
    let relative: String
    let unread: Int
}

struct ChatMessage: Identifiable, Hashable {
    let id: Int
    let body: String
    let relative: String
    let mine: Bool
}

struct MessagesInboxView: View {
    @EnvironmentObject private var session: AppSession
    @State private var threads: [MessageThread] = []
    @State private var loading = false
    @State private var errorText: String?
    @State private var showLogin = false

    var body: some View {
        NavigationStack {
            Group {
                if !session.isLoggedIn {
                    loginGate
                } else if loading && threads.isEmpty {
                    ProgressView("Ucitavanje poruka...").tint(Brand.green)
                        .frame(maxWidth: .infinity, maxHeight: .infinity)
                } else if let errorText = errorText, threads.isEmpty {
                    Text(errorText).foregroundColor(.secondary).padding()
                } else if threads.isEmpty {
                    Text("Nema poruka jos.")
                        .foregroundColor(Brand.muted)
                        .frame(maxWidth: .infinity, maxHeight: .infinity)
                } else {
                    List(threads) { t in
                        NavigationLink(value: t) {
                            VStack(alignment: .leading, spacing: 6) {
                                HStack {
                                    Text(t.partnerName.isEmpty ? "Korisnik" : t.partnerName)
                                        .font(.system(size: 16, weight: .bold))
                                    Spacer()
                                    Text(t.relative)
                                        .font(.caption)
                                        .foregroundColor(Brand.muted)
                                }
                                Text(t.adTitle)
                                    .font(.subheadline)
                                    .foregroundColor(Brand.greenDark)
                                    .lineLimit(1)
                                HStack {
                                    Text(t.lastBody)
                                        .font(.footnote)
                                        .foregroundColor(Brand.muted)
                                        .lineLimit(2)
                                    if t.unread > 0 {
                                        Text("\(t.unread)")
                                            .font(.caption2.weight(.bold))
                                            .foregroundColor(.white)
                                            .padding(.horizontal, 6)
                                            .padding(.vertical, 2)
                                            .background(Color.red)
                                            .clipShape(Capsule())
                                    }
                                }
                            }
                            .padding(.vertical, 4)
                        }
                    }
                    .listStyle(.plain)
                }
            }
            .background(Brand.bg.ignoresSafeArea())
            .navigationTitle("Poruke")
            .navigationDestination(for: MessageThread.self) { t in
                MessageThreadView(thread: t)
            }
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button {
                        Task { await load() }
                    } label: {
                        Image(systemName: "arrow.clockwise")
                    }
                }
            }
            .sheet(isPresented: $showLogin) {
                NavigationStack {
                    LoginView().environmentObject(session)
                }
            }
            .task {
                if session.isLoggedIn {
                    await load()
                }
            }
            .onChange(of: session.isLoggedIn) { logged in
                if logged { Task { await load() } } else { threads = [] }
            }
        }
    }

    private var loginGate: some View {
        VStack(spacing: 16) {
            Image(systemName: "bubble.left.and.bubble.right.fill")
                .font(.system(size: 40))
                .foregroundColor(Brand.green)
            Text("Prijavi se da vidis poruke")
                .font(.headline)
            Button("Prijava") { showLogin = true }
                .buttonStyle(.borderedProminent)
                .tint(Brand.green)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }

    private func load() async {
        loading = true
        errorText = nil
        defer { loading = false }
        do {
            let json = try await KTNetwork.getJSON("/api/messages.php?action=threads") as? [String: Any]
            guard let ok = json?["ok"] as? Bool, ok,
                  let arr = json?["threads"] as? [[String: Any]] else {
                throw KTAuthError.unknown("Ne mogu da ucitam poruke.")
            }
            threads = arr.compactMap { row in
                MessageThread(
                    key: row["key"] as? String ?? UUID().uuidString,
                    adId: row["ad_id"] as? Int ?? 0,
                    partnerId: row["partner_id"] as? Int ?? 0,
                    partnerName: row["partner_name"] as? String ?? "",
                    adTitle: row["ad_title"] as? String ?? "",
                    lastBody: row["last_body"] as? String ?? "",
                    relative: row["relative"] as? String ?? "",
                    unread: row["unread"] as? Int ?? 0
                )
            }
            await session.refreshBadges()
        } catch {
            if error is KTAuthError {
                session.isLoggedIn = false
            }
            errorText = error.localizedDescription
        }
    }
}

struct MessageThreadView: View {
    @EnvironmentObject private var session: AppSession
    let thread: MessageThread
    @State private var messages: [ChatMessage] = []
    @State private var draft = ""
    @State private var loading = false

    var body: some View {
        VStack(spacing: 0) {
            ScrollViewReader { proxy in
                ScrollView {
                    LazyVStack(alignment: .leading, spacing: 10) {
                        ForEach(messages) { msg in
                            HStack {
                                if msg.mine { Spacer(minLength: 40) }
                                VStack(alignment: msg.mine ? .trailing : .leading, spacing: 4) {
                                    Text(msg.body)
                                        .padding(12)
                                        .background(msg.mine ? Brand.green : Color.white)
                                        .foregroundColor(msg.mine ? .white : Brand.text)
                                        .cornerRadius(14)
                                    Text(msg.relative)
                                        .font(.caption2)
                                        .foregroundColor(Brand.muted)
                                }
                                if !msg.mine { Spacer(minLength: 40) }
                            }
                            .id(msg.id)
                        }
                    }
                    .padding(14)
                }
                .onChange(of: messages.count) { _ in
                    if let last = messages.last {
                        proxy.scrollTo(last.id, anchor: .bottom)
                    }
                }
            }

            HStack(spacing: 10) {
                TextField("Poruka...", text: $draft)
                    .padding(12)
                    .background(Brand.bg)
                    .cornerRadius(12)
                Button {
                    Task { await send() }
                } label: {
                    Image(systemName: "paperplane.fill")
                        .foregroundColor(.white)
                        .frame(width: 44, height: 44)
                        .background(Brand.green)
                        .clipShape(Circle())
                }
                .disabled(draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)
            }
            .padding(12)
            .background(Color.white)
        }
        .background(Brand.bg.ignoresSafeArea())
        .navigationTitle(thread.partnerName.isEmpty ? "Razgovor" : thread.partnerName)
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
    }

    private func load() async {
        loading = true
        defer { loading = false }
        do {
            let path = "/api/messages.php?action=thread&ad=\(thread.adId)&with=\(thread.partnerId)"
            let json = try await KTNetwork.getJSON(path) as? [String: Any]
            let arr = json?["messages"] as? [[String: Any]] ?? []
            messages = arr.compactMap { row in
                ChatMessage(
                    id: row["id"] as? Int ?? Int.random(in: 1...999999),
                    body: row["body"] as? String ?? "",
                    relative: row["relative"] as? String ?? "",
                    mine: row["mine"] as? Bool ?? false
                )
            }
        } catch {
            // keep empty
        }
    }

    private func send() async {
        let text = draft.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !text.isEmpty else { return }
        draft = ""
        let csrf = await session.ensureCSRF()
        do {
            let json = try await KTNetwork.postJSON(
                path: "/api/messages.php?action=send",
                fields: [
                    "action": "send",
                    "ad_id": String(thread.adId),
                    "to_user_id": String(thread.partnerId),
                    "message": text
                ],
                csrf: csrf
            )
            if let msg = json["message"] as? [String: Any] {
                messages.append(
                    ChatMessage(
                        id: msg["id"] as? Int ?? Int.random(in: 1...999999),
                        body: msg["body"] as? String ?? text,
                        relative: msg["relative"] as? String ?? "sada",
                        mine: true
                    )
                )
            } else {
                await load()
            }
        } catch {
            draft = text
        }
    }
}
