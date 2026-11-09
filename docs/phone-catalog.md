# Katalog telefona

Javne adrese su `/katalog` i `/katalog/{marka}/{model}`, na primer `/katalog/apple/iphone-15`. Iste putanje važe i kroz `router.php` i kroz `public/index.php`, koji Nginx koristi kao rezervu.

Katalog čita aktivne oglase u trenutku otvaranja stranice. Promena cene ili stanja, novi oglas sa izabranim modelom i obrisan ili prodat oglas odmah menjaju broj i raspon. Nema posebnog keša cena.

## Šta je u prvoj verziji

U `data/phone_catalog_seed.json` je kontrolisan skup modela. To nije spisak svih telefona.

- **iPhone 15** ima specifikacije i kapacitete 128/256/512 GB iz Apple Support stranice [iPhone 15 - Tech Specs](https://support.apple.com/en-us/111831), preuzete 2026-11-06. U katalog je prenet skraćen izvod, ne cela stranica. Radna memorija nije navedena jer je Apple na toj stranici ne objavljuje.
- **Ostali modeli** imaju samo identitet (marka i naziv) potvrđen preko Wikidata stavke. Ekran, baterija, RAM i službene varijante memorije nisu popunjeni i ne smeju se pogađati.
- Nema službenih fotografija. Kartica koristi fotografiju iz povezanog oglasa ili prazan znak marke.
- Nisu korišćeni GSMArena, Mobilni svet, tuđe baze ni plaćeni API. Proizvođačka stranica je navedena kao izvor jednog modela, nije meta masovnog skidanja.

Model koji nije u seedu vraća 404. Cena se ne izmišlja: ako nema aktivnog oglasa, stranica to kaže. „Po dogovoru“ i „Na kontakt“ ulaze u broj oglasa, ali ne u minimum, medijanu i maksimum. Kupovina, zamena, prodati i neaktivni oglasi se ne računaju.

## Skladište

Živi podaci i dalje idu kroz postojeći sloj:

- `STORAGE_DRIVER=json` čuva dokumente u `data/`
- `STORAGE_DRIVER=mysql` čuva iste dokumente u tabeli `json_documents`

Uvoz pravi dokument `phone_models.json`. Dok tog dokumenta nema, sajt čita seed iz repoa. Oglasi dobijaju samo dodatna polja:

| Polje | Značenje |
| --- | --- |
| `phone_model_id` | Model iz kataloga, na primer `apple-iphone-15` |
| `phone_variant_id` | Službena varijanta memorije, samo kad je ima u izvoru i poklapa se sa oglasom |
| `phone_storage_norm` | Kapacitet pročitan iz polja ili naslova oglasa (`128GB`) |
| `phone_model_link` | Kako je veza nastala: `seller`, `manual` ili `migration` |

`database/phone_catalog_upgrade.sql` dodaje relacione tabele izvora, modela, varijanti i specifikacija. Ne menja postojeće kolone oglasa. Relaciona šema je opcioni ogled; stranice je ne traže.

## Uvoz

Iz korena repoa:

```bash
php tools/import_phone_catalog.php
php tools/import_phone_catalog.php --apply
php tools/import_phone_catalog.php --apply --sql
```

Bez `--apply` ništa se ne upisuje. Provera odbija specifikaciju bez izvora i sliku bez licence `cc0`, `cc-by` ili `cc-by-sa`. `--sql` traži MySQL iz `.env`. Ako baza nije dostupna, JSON uvoz i dalje prolazi, a SQL se preskače.

Novi model se dodaje u seed: `id`, marka, slugovi, bar jedan `source_ids` zapis i aliasi. Specifikacija mora imati `source_id` koji postoji u `sources`. Posle izmene seeda ponovo pokrenuti `--apply` ako dokument `phone_models.json` već postoji, jer on ima prednost nad seedom.

## Wikidata sync

Masovno punjenje modela ide preko Wikidata (ne GSMArena / Mobilni svet):

```bash
php tools/sync_phone_catalog_wikidata.php
php tools/sync_phone_catalog_wikidata.php --apply
```

Ili u adminu: **Katalog telefona** → Pregledaj sync → Primeni sync (`/admin_phone_catalog.php`).

Sync povlači smartphone modele odabranih brendova od 2016. godine. Upisuje dimenzije, težinu, dijagonalu, čipset i memorijske varijante **samo ako postoje na Wikidata stavci**. Proizvođačke specifikacije (npr. Apple Support) se ne prepisuju. Prazna polja se ne pogađaju.

## Povezivanje postojećih oglasa

```bash
php tools/link_phone_models.php
php tools/link_phone_models.php --apply
```

Dry-run ispisuje brojeve i pregled. `--apply` menja samo četiri polja veze. Naslov, opis, cena, telefon, grad, slike i ostala javna polja ostaju. Alat preskače satove, tablete, AirPods, maske, ekrane, flex i rezervne delove. Veza koju je prodavac izabrao (`seller`) ili ručni unos (`manual`) ne prepisuje se. Ako se poklapanje razlikuje od već upisane migracije, prijavljuje se sukob i oglas se ne menja.

Poklapanje je tačan naziv, ne približna pretraga. „iPhone 15“ ne uzima „iPhone 15 Pro“. Posle izmene kataloga komanda se može ponoviti.

## Forma oglasa

Na telefonu prodavac bira model ili ostavlja „Ručni unos“. Izbor upisuje marku, naziv i, ako je izabrana, službenu memoriju. Opis, stanje, cena, grad i fotografije se ne popunjavaju iz kataloga. Nepoznat `phone_model_id` se odbija. Tablet, sat i delovi ne dobijaju vezu. Stari oglas bez modela i dalje može da se sačuva.

## Testovi

```bash
php tests/phone_catalog_test.php
php tests/shop_catalog_test.php
php tests/listing_detail_test.php
```

Lokalni pregled:

```bash
php -S localhost:8080 router.php
```

Ova izmena se ne deploy-uje sama. Produkciju i oglase 1162/1167 ne treba dirati ovim korakom.
