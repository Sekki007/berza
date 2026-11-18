# KupiTelefon iOS (Swift Playgrounds, bez Maca)

SwiftUI App projekat koji otvara [kupitelefon.rs](https://kupitelefon.rs) u `WKWebView` — isti pristup kao Android Capacitor wrapper.

Projekat: [`KupiTelefon.swiftpm`](./KupiTelefon.swiftpm/)

## Šta treba na iPadu

- **iPad 9** (ili noviji) sa iPadOS 16+
- **Swift Playgrounds** 4+ (besplatno iz App Store)
- Apple ID (za pokretanje app-a na uređaju)
- Preporuka: app **Working Copy** (Git klijent) ili Files + zip

## Tok rada (Windows → GitHub → iPad)

```text
Cursor (Windows)  →  git push  →  GitHub berza
                                      ↓
                         Working Copy / Files na iPadu
                                      ↓
                         Swift Playgrounds → Run
```

### 1. Na PC-u (ovaj repo)

Kod je već u:

`mobile/ios-playgrounds/KupiTelefon.swiftpm/`

Posle izmena: commit + push na `https://github.com/Sekki007/berza.git`.

### 2. Na iPadu — Working Copy (preporučeno)

1. Instaliraj **Working Copy**.
2. Clone `https://github.com/Sekki007/berza.git` (HTTPS ili SSH).
3. U Files / Working Copy pronađi folder  
   `berza/mobile/ios-playgrounds/KupiTelefon.swiftpm`.
4. Share / Open in **Swift Playgrounds**  
   (ili u Playgrounds: Open → Files → taj `.swiftpm` folder).

### 3. Alternativa bez Working Copy

1. Na GitHubu: Code → Download ZIP.
2. Airdrop / Files / iCloud na iPad.
3. Raspakuj, otvori `KupiTelefon.swiftpm` u Swift Playgrounds.

### 4. Pokretanje

1. Otvori projekat u Swift Playgrounds.
2. Sačekaj resolve paketa (prvi put može potrajati).
3. Tapni **Run** (▶).
4. Treba da se učita sajt `kupitelefon.rs`.

**Beli ekran / nema mreže:** u Swift Playgrounds → App Settings uključi **Outgoing Connections / Network**. Manifest već ima `.outgoingNetworkConnections()`.

**Važno:** otvaraj tačno folder `KupiTelefon.swiftpm` (ne parent `ios-playgrounds`). Posle `git pull` zatvori projekat u Playgrounds pa ga otvori ponovo.

Ako i dalje crashuje: u Playgrounds napravi **New App**, pa zameni `ContentView.swift` / dodaj `WebView.swift` sadržajem iz ovog repoa.

## Šta app radi (hibrid)

- **Oglasi** — native SwiftUI lista (čita javni HTML sa kupitelefon.rs, **bez izmene sajta**)
- Filter: Sve / Telefoni / Delovi / Servisi + pretraga
- Tap na oglas → sheet sa WebView detaljem
- Tabovi **Pretraga / Besplatno / Nalog** → WebView (forma i nalog još nemaju API)
- Outgoing network capability obavezna

## Šta još nije

- App Store upload (Apple Developer + kasnije Mac/Xcode)
- Push notifikacije
- Potpuni native detalj / prijava / postavi oglas (treba API — trenutno namerno ne diramo sajt)

## Razvoj

- Swift fajlove uređuj u Cursoru na Windowsu.
- Push → na iPadu pull u Working Copy → ponovo Run u Playgrounds.
- Ne očekuj lokalni iOS build na Windowsu — Run ide samo na iPadu.
