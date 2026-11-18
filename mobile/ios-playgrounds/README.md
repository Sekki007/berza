# KupiTelefon iOS (Swift Playgrounds)

Hybrid app: **native Oglasi feed** + WebView za detalj / poruke / postavi / nalog.  
**Sajt se ne menja** — app čita javni HTML i postojeće stranice.

Projekat: [`KupiTelefon.swiftpm`](./KupiTelefon.swiftpm/)

## Tabovi (kao mockup)

1. **Oglasi** — native lista, kategorije, pretraga, bell/heart  
2. **Pretraga** — hub linkova (filteri, katalog, IMEI…)  
3. **Besplatno** — WebView `/postavi-oglas`  
4. **Poruke** — WebView `/poruke.php`  
5. **Nalog** — native hub kartica → WebView ekrani

## iPad

1. Pull `https://github.com/Sekki007/berza`  
2. Otvori `mobile/ios-playgrounds/KupiTelefon.swiftpm` u Swift Playgrounds  
3. Uključi **Outgoing Connections / Network** ako treba  
4. Run

## Napomena

Pun native detalj/login/poruke traži API kasnije. Ova verzija je vizuelni shell da se vidi kako app izgleda.
