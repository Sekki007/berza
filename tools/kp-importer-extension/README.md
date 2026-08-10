# KP → KupiTelefon Chrome ekstenzija

Skuplja oglase sa **KupujemProdajem** izloga u JSON fajl. Kasnije: admin uvoz na KupiTelefon.rs za određenog korisnika.

## Instalacija (developer mode)

1. Otvori Chrome → `chrome://extensions`
2. Uključi **Developer mode**
3. **Load unpacked** → izaberi folder:
   ```
   C:\Projekti\berza\tools\kp-importer-extension
   ```

## Korišćenje

1. Na KP otvori stranicu **Svi oglasi** prodavca, npr.  
   `https://www.kupujemprodajem.com/beli-facemobile/svi-oglasi/230131/1`
2. Klikni ikonicu ekstenzije
3. **Pokupi oglase**
   - *Sve stranice* — prolazi paginaciju (1, 2, 3…)
   - *Detalji* — pun opis + sve slike iz galerije (sporije)
   - **Slike sa liste** uvek idu u JSON (thumbnail + puna rezolucija kad postoji)
4. **Preuzmi JSON**

## Format JSON-a

```json
{
  "exported_at": "2026-08-10T...",
  "source": "kupujemprodajem",
  "seller": {
    "username": "beli-facemobile",
    "user_id": "230131",
    "reviews_positive": 1404,
    "reviews_negative": 1
  },
  "ads": [
    {
      "source_id": "123456",
      "source_url": "https://...",
      "title": "...",
      "price": 760,
      "currency": "EUR",
      "location": "Beograd",
      "description": "...",
      "images": ["https://..."]
    }
  ],
  "meta": { "pages_scraped": 5, "total_ads": 137, "errors": [] }
}
```

## Filteri (v1.3+)

- **Samo telefoni** — zadržava mobilne telefone (URL `/mobilni-telefoni/` + heuristika naslova)
- **Telefoni + satovi + tableti** — uređaji bez maski/punjača
- **Skip** — lista reči (torbica, maska, laptop…) — oglas se baca ako naslov sadrži reč
- **Max stranica** — za izloge sa 300+ strana (npr. 10–20 po rundi)

Filter se primenjuje **pre** učitavanja opisa/detalja — brže i manje zahteva ka KP.


## Sledeći korak (KupiTelefon)

Admin panel: upload JSON + izbor korisnika → import oglasa.
