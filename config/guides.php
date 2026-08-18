<?php

declare(strict_types=1);

function getAllGuides(): array
{
    $rows = readJsonFile('guides.json');
    if ($rows === []) {
        $rows = defaultGuidesSeed();
        if ($rows !== []) {
            writeJsonFile('guides.json', $rows);
        }
    } else {
        $rows = upgradeDefaultGuides($rows);
    }
    usort($rows, static fn($a, $b) => strcmp((string)($b['updated_at'] ?? $b['created_at'] ?? ''), (string)($a['updated_at'] ?? $a['created_at'] ?? '')));
    return $rows;
}

function defaultGuideContent(): array
{
    return [
        'provera-polovnog-iphone-a' => <<<'HTML'
<p>Kupovina polovnog iPhone-a može da bude odličan način da dobiješ kvalitetan telefon za manje novca, ali samo ako uređaj proveriš pre plaćanja. Lepo kućište i dobra fotografija nisu dokaz da je telefon potpuno ispravan. Najskuplji problemi često se ne vide spolja: zaključan iCloud nalog, neoriginalan ekran, oslabila baterija, neispravan Face ID ili tragovi vlage.</p>

<h2>1. Proveri prodavca i oglas pre susreta</h2>
<p>Pre nego što kreneš na dogovoreno mesto, pogledaj koliko dugo prodavac ima nalog, kakve ocene poseduje i da li su podaci u oglasu jasni. Oglas treba da navede tačan model, memoriju, stanje baterije, eventualne popravke i poznate nedostatke. Ako prodavac izbegava odgovore ili insistira da uplatiš novac unapred, zastani i traži sigurniju opciju.</p>
<ul>
  <li>Traži fotografije stvarnog uređaja, ne promotivne slike sa interneta.</li>
  <li>Pitaj da li su ekran, baterija, kamera ili kućište ikada menjani.</li>
  <li>Dogovori da telefon možeš mirno da testiraš najmanje 15 minuta.</li>
  <li>Ako postoji račun ili garancija, traži da ih vidiš pre plaćanja.</li>
</ul>

<h2>2. Potvrdi model, memoriju i IMEI</h2>
<p>Otvori <strong>Settings → General → About</strong>. Tu proveri naziv modela, kapacitet memorije, serijski broj i IMEI. IMEI u telefonu treba da se podudara sa brojem na kutiji i računu, ako ih prodavac ima. Razlika ne mora automatski da znači prevaru, ali mora da ima logično i proverljivo objašnjenje.</p>
<p>Serijski broj možeš proveriti i na Apple stranici za status garancije. Obrati pažnju ako sistem ne prepoznaje broj ili ako podaci ne odgovaraju ponuđenom modelu. Ne kupuj telefon čiji je IMEI prijavljen kao izgubljen, ukraden ili blokiran kod operatera.</p>

<h2>3. Activation Lock i iCloud su najvažnija provera</h2>
<p>Telefon mora da bude odjavljen sa naloga prethodnog vlasnika. Prodavac treba pred tobom da isključi <strong>Find My iPhone</strong>, odjavi Apple ID i obriše sadržaj preko opcije <strong>Erase All Content and Settings</strong>. Posle resetovanja započni početno podešavanje i poveži uređaj na internet.</p>
<p>Ako se pojavi zahtev za lozinku prethodnog vlasnika, uređaj ima Activation Lock. Takav telefon ne plaćaj, čak i ako prodavac obeća da će nalog ukloniti kasnije. Bez naloga vlasnika telefon može ostati neupotrebljiv.</p>

<h2>4. Proveri ekran i dodir</h2>
<p>Povećaj osvetljenje, otvori belu i tamnu pozadinu i pregledaj ceo ekran. Traži mrtve piksele, fleke, treperenje, senke i neujednačene boje. Prevuci ikonicu ili prst preko svih delova ekrana kako bi proverio da li dodir reaguje svuda.</p>
<ul>
  <li>Proveri True Tone u podešavanjima ekrana; njegov izostanak može ukazivati na zamenu ekrana, ali nije sam po sebi konačan dokaz.</li>
  <li>Ukucaj tekst preko cele tastature da otkriješ mrtve zone dodira.</li>
  <li>Zaključaj i otključaj telefon više puta i proveri senzor osvetljenja.</li>
  <li>Pogledaj ekran pod uglom: loš zamenski panel često ima slabije boje i kontrast.</li>
</ul>

<h2>5. Face ID, Touch ID i kamere</h2>
<p>Dodaj svoje lice u Face ID i probaj otključavanje više puta iz različitih uglova. Poruka da Face ID nije dostupan može da znači skup kvar ili posledicu udarca i nestručne popravke. Kod starijih modela proveri Touch ID na isti način.</p>
<p>Testiraj prednju i sve zadnje kamere, uključujući širokougaonu i telefoto kameru ako ih model ima. Snimi fotografiju, video sa zvukom i kratki snimak pri slabijem svetlu. Fokus treba da radi brzo, slika ne sme da podrhtava, a u kadru ne bi trebalo da postoje trajne mrlje.</p>

<h2>6. Baterija i punjenje</h2>
<p>U <strong>Settings → Battery → Battery Health</strong> proveri maksimalni kapacitet. Vrednost od 85% ili više je uglavnom prihvatljiva za polovan uređaj; ispod 80% računaj da će uskoro biti potrebna zamena. Važnije od samog procenta je da nema upozorenja o nepoznatom delu ili problemu sa baterijom.</p>
<p>Ponesi kabl ili punjač i proveri Lightning/USB-C priključak. Telefon ne sme da prekida punjenje pri blagom pomeranju kabla. Ako model podržava bežično punjenje, proveri i njega. Obrati pažnju na pregrevanje tokom nekoliko minuta korišćenja kamere ili zahtevnije aplikacije.</p>

<h2>7. Poziv, mreža, Wi‑Fi i zvuk</h2>
<p>Ubaci svoju SIM karticu, obavi poziv i proveri mobilni internet. Testiraj glavni i razgovorni zvučnik, oba mikrofona, Wi‑Fi, Bluetooth i GPS. Isključi Wi‑Fi tokom testa kako bi bio siguran da mobilni internet zaista radi.</p>
<ol>
  <li>Pozovi nekoga i proveri da li se jasno čujete sa obe strane.</li>
  <li>Pusti muziku i postepeno pojačaj zvuk; ne sme biti krčanja.</li>
  <li>Snimi glasovnu belešku i preslušaj je.</li>
  <li>Poveži Bluetooth slušalice ili drugi uređaj.</li>
  <li>Otvori mapu i proveri da li lokacija prati tvoje kretanje.</li>
</ol>

<h2>8. Kućište, dugmad i tragovi tečnosti</h2>
<p>Pregledaj ivice, šrafove i spojeve između ekrana i rama. Nejednaki zazori, tragovi lepka ili oštećeni šrafovi mogu da znače da je telefon otvaran. Proveri dugmad za zvuk, mute prekidač ili Action dugme, bočno dugme i vibraciju. Pogledaj sočiva kamera pod svetlom i uveri se da nisu napukla.</p>
<p>Otpornost na vodu se ne može pouzdano garantovati na polovnom telefonu, posebno ako je otvaran. Zato ne testiraj uređaj potapanjem. Ako sumnjaš na vlagu ili koroziju, odnesi ga u servis na dijagnostiku pre kupovine.</p>

<h2>9. Parts and Service History</h2>
<p>Na novijim verzijama iOS-a u odeljku <strong>Settings → General → About</strong> može se pojaviti „Parts and Service History“. Tu se vidi da li su menjani baterija, ekran ili kamera i da li sistem prepoznaje deo kao originalan. Zamena dela nije nužno loša ako je urađena kvalitetno, ali mora da utiče na cenu i prodavac treba da je prijavi.</p>

<h2>10. Završna check-lista pre plaćanja</h2>
<ul>
  <li>Apple ID prethodnog vlasnika je uklonjen i telefon prolazi aktivaciju.</li>
  <li>IMEI i model odgovaraju oglasu, kutiji i računu.</li>
  <li>Ekran, dodir, Face ID/Touch ID i sve kamere rade.</li>
  <li>Baterija nema upozorenja i prihvatljivog je kapaciteta.</li>
  <li>Pozivi, mobilni internet, Wi‑Fi, Bluetooth, GPS i zvuk rade.</li>
  <li>Dogovorena cena odgovara stanju i eventualnim zamenjenim delovima.</li>
</ul>

<p>Aktuelnu ponudu pogledaj među <a href="/oglasi/apple">Apple oglasima</a>. Ako nisi siguran u stanje uređaja, pronađi <a href="/oglasi/servis">servisni oglas</a> i dogovori stručnu dijagnostiku pre kupovine. Nekoliko minuta detaljne provere može da spreči mnogo skuplji problem.</p>
HTML,
        'bezbedna-kupovina-telefona' => <<<'HTML'
<p>Polovan ili nov telefon preko oglasa često može da se kupi povoljnije nego u prodavnici, ali dobra cena ne treba da bude važnija od bezbedne kupovine. Najčešće prevare oslanjaju se na žurbu, uplatu unapred i nedovoljno proverene podatke. Cilj ovog vodiča je da ti pomogne da prepoznaš rizičan oglas, proveriš prodavca i sačuvaš dokaze o dogovoru.</p>

<h2>1. Prepoznaj oglas koji zahteva dodatni oprez</h2>
<p>Niska cena sama po sebi nije dokaz prevare, ali veoma velika razlika u odnosu na ostale oglase mora da ima realno objašnjenje: oštećenje, slabija baterija, zaključan uređaj ili hitna prodaja. Oglas bez stvarnih fotografija, sa malo informacija i pritiskom da odmah uplatiš novac predstavlja visok rizik.</p>
<ul>
  <li>Uporedi cenu sa najmanje pet sličnih aktivnih oglasa.</li>
  <li>Pretraži deo teksta oglasa i fotografije; prevaranti često kopiraju tuđe oglase.</li>
  <li>Proveri da li se lokacija, broj telefona i priča prodavca menjaju tokom razgovora.</li>
  <li>Ne veruj automatski fotografiji lične karte — i ona može biti ukradena.</li>
</ul>

<h2>2. Proveri profil i reputaciju prodavca</h2>
<p>Pogledaj datum registracije, broj aktivnih oglasa, ocene kupaca i način na koji prodavac odgovara na pitanja. Stariji nalog sa doslednim ocenama smanjuje rizik, ali ga ne uklanja potpuno. Kod firmi proveri naziv, PIB, adresu i da li se podaci podudaraju sa javno dostupnim informacijama.</p>
<p>Na KupiTelefon karticama i izlogu možeš videti zbir pozitivnih i negativnih ocena. Otvori profil prodavca i pročitaj komentare, posebno ako kupuješ skuplji uređaj. Jedna loša ocena ne mora da bude presudna, ali ponovljene primedbe na isti problem jesu ozbiljno upozorenje.</p>

<h2>3. Razgovor vodi kroz platformu</h2>
<p>Poruke unutar platforme ostavljaju trag o tome šta je ponuđeno i dogovoreno. U poruci jasno potvrdi model, memoriju, stanje, dodatnu opremu, cenu i način isporuke. Ako razgovor pređe na telefon ili drugu aplikaciju, najvažnije detalje ponovo napiši u poruci.</p>
<blockquote><p>Primer: „Potvrđujem da kupujem iPhone 14 Pro 128 GB, bez iCloud zaključavanja, sa ispravnim Face ID-em i baterijom 87%, po ceni od 520 €. U cenu ulaze kutija i kabl.“</p></blockquote>
<p>Ne šalji fotografiju platne kartice, sigurnosni kod, lozinku, SMS kod niti podatke za prijavu na e-banking. Za prijem uplate prodavcu je dovoljan broj računa; ne treba mu broj kartice ni jednokratni kod.</p>

<h2>4. Lažni kuriri i phishing stranice</h2>
<p>Česta prevara je poruka sa linkom koji navodno vodi na stranicu kurirske službe radi „potvrde uplate“ ili „preuzimanja novca“. Link vodi na lažnu stranicu koja traži broj kartice, datum važenja, CVV ili SMS kod.</p>
<ul>
  <li>Ne otvaraj linkove za naplatu koje šalje nepoznata osoba.</li>
  <li>Sam ukucaj adresu zvaničnog sajta kurirske službe.</li>
  <li>Proveri domen slovo po slovo; katanac u browseru ne znači da je sajt legitiman.</li>
  <li>Kurir ne traži PIN, CVV ni kod iz SMS poruke da bi ti isplatio novac.</li>
</ul>

<h2>5. Lično preuzimanje je najsigurnije</h2>
<p>Za skuplje telefone preporučeno je lično preuzimanje na prometnom i osvetljenom mestu, idealno u servisu ili prostoru gde uređaj možeš detaljno testirati. Nemoj pristati da se susret obavi u žurbi, na parkingu bez mogućnosti provere ili pod pritiskom da odmah platiš.</p>
<p>Ponesi SIM karticu, punjač, kabl i po potrebi laptop. Proveri identitet uređaja, mrežu, ekran, kamere, zvuk, bateriju i naloge. Za iPhone koristi detaljnu <a href="/vodic/provera-polovnog-iphone-a">check-listu za proveru polovnog iPhone-a</a>. Za Android proveri da li je Google nalog uklonjen i da li telefon posle resetovanja može normalno da se aktivira.</p>

<h2>6. Ako kupuješ dostavom</h2>
<p>Dostava nosi veći rizik jer uređaj ne možeš unapred potpuno da proveriš. Traži dodatne fotografije i video snimak na kome se vidi da telefon radi, zajedno sa papirom na kome je napisan aktuelni datum ili dogovorena reč. Dogovori slanje sa mogućnošću pregleda ako kurirska služba to podržava, ali znaj da kratak pregled paketa često nije dovoljan za kompletan test uređaja.</p>
<ol>
  <li>Sačuvaj oglas, poruke, broj pošiljke i potvrdu o uplati.</li>
  <li>Snimi neprekinut video otvaranja paketa od trenutka kada pokažeš zatvorenu ambalažu.</li>
  <li>Proveri da li se model i IMEI podudaraju sa dogovorom.</li>
  <li>Odmah prijavi vidljivo oštećenje kuriru i prodavcu.</li>
</ol>

<h2>7. Plaćanje i dokaz o kupovini</h2>
<p>Kod ličnog preuzimanja plati tek nakon provere. Za veće iznose korisno je napraviti jednostavnu potvrdu koja sadrži ime prodavca i kupca, model, IMEI, cenu, datum i potpise. Kod firme traži fiskalni račun ili drugi odgovarajući dokument.</p>
<p>Izbegavaj nepovratne metode plaćanja nepoznatim osobama. Kapara ima smisla samo kada razumeš kome plaćaš, zašto je potrebna i pod kojim uslovima se vraća. „Rezervacija“ telefona uz hitnu uplatu je čest oblik prevare.</p>

<h2>8. Crvene zastavice zbog kojih treba odustati</h2>
<ul>
  <li>Prodavac odbija poziv, dodatne slike ili proveru uživo.</li>
  <li>Traži uplatu unapred i tvrdi da postoji drugi kupac koji čeka.</li>
  <li>Šalje link na kome treba da uneseš podatke kartice ili SMS kod.</li>
  <li>IMEI, kutija i telefon se ne podudaraju, a nema jasnog objašnjenja.</li>
  <li>Telefon je vezan za tuđi iCloud ili Google nalog.</li>
  <li>Priča o poreklu, računu ili stanju uređaja se menja.</li>
</ul>

<h2>9. Šta uraditi ako posumnjaš na prevaru</h2>
<p>Prekini komunikaciju i ne šalji dodatni novac. Sačuvaj screenshot oglasa, profil, poruke, broj telefona, linkove, podatke računa i potvrde o uplati. Prijavi oglas platformi. Ako si već uneo podatke kartice na sumnjivom sajtu, odmah pozovi banku, blokiraj karticu i promeni kompromitovane lozinke. Ako je novac već poslat ili postoji krađa identiteta, obrati se policiji i dostavi sve sačuvane dokaze.</p>

<h2>Kratka bezbednosna check-lista</h2>
<ul>
  <li>Uporedio sam cenu i proverio originalnost fotografija.</li>
  <li>Pregledao sam profil, ocene i druge oglase prodavca.</li>
  <li>Važne detalje dogovora imam zapisane u porukama.</li>
  <li>Nisam slao lozinke, PIN, CVV ili SMS kod.</li>
  <li>Uređaj plaćam tek nakon provere ili koristim razuman, dokaziv način kupovine.</li>
</ul>

<p>Pregledaj aktuelne <a href="/oglasi/telefoni">oglase za telefone</a> i koristi filtere da uporediš realne cene. Ako želiš stručnu proveru pre plaćanja, pronađi servis kroz <a href="/servisi">imenik firmi</a> ili među <a href="/oglasi/servis">servisnim oglasima</a>.</p>
HTML,
        'kada-se-isplati-zamena-ekrana' => <<<'HTML'
<p>Napukao ekran ne znači automatski da moraš da kupiš novi telefon. Kod novijeg i vrednijeg uređaja kvalitetna zamena ekrana često je najpametniji izbor. Sa druge strane, kod starijeg telefona sa oslabljenom baterijom i drugim kvarovima popravka može da košta više nego što uređaj realno vredi.</p>

<h2>Prvo utvrdi šta je zapravo oštećeno</h2>
<p>„Razbijen ekran“ može da znači nekoliko različitih kvarova. Od vrste oštećenja zavisi postupak i cena popravke:</p>
<ul>
  <li><strong>Napuklo je samo zaštitno staklo</strong>, ali slika i dodir rade normalno.</li>
  <li><strong>Oštećen je LCD/OLED panel</strong>: pojavljuju se linije, crne fleke, treperenje ili nema slike.</li>
  <li><strong>Dodir ne radi</strong> na delu ili celom ekranu.</li>
  <li><strong>Oštećen je ram</strong>, pa novi ekran ne može pravilno da nalegne.</li>
</ul>
<p>Kod nekih modela moguće je zameniti samo staklo, ali to zahteva specijalizovanu opremu i očuvan panel. Češće se menja kompletan sklop ekrana. Pošalji servisu jasne fotografije i opiši da li slika i dodir rade, ali traži konačnu procenu tek nakon pregleda uređaja.</p>

<h2>Jednostavna računica isplativosti</h2>
<p>Uporedi ukupnu cenu popravke sa realnom tržišnom vrednošću ispravnog polovnog telefona. U ukupnu cenu uključi ekran, rad, eventualni novi ram, lepak, dostavu i druge kvarove otkrivene tokom dijagnostike.</p>
<p>Kao praktična smernica:</p>
<ul>
  <li>Do približno <strong>30–40% vrednosti ispravnog uređaja</strong>, popravka se najčešće isplati ako je ostatak telefona dobar.</li>
  <li>Između <strong>40% i 60%</strong>, odluka zavisi od baterije, starosti, podrške za ažuriranja i toga koliko dugo planiraš da koristiš telefon.</li>
  <li>Preko <strong>60%</strong>, ozbiljno uporedi popravku sa kupovinom drugog uređaja.</li>
</ul>
<p>Ovo nije strogo pravilo. Ako telefon ima važne podatke, poslovne aplikacije ili dodatnu opremu koju želiš da nastaviš da koristiš, popravka može imati smisla i pri većem procentu.</p>

<h2>Kada se zamena ekrana najčešće isplati</h2>
<ul>
  <li>Telefon je relativno nov i još dobija bezbednosna ažuriranja.</li>
  <li>Baterija je u dobrom stanju, a kamere, mreža, punjenje i matična ploča rade.</li>
  <li>Uređaj nema tragove tečnosti i ozbiljno iskrivljen ram.</li>
  <li>Može se nabaviti kvalitetan ekran uz pisanu garanciju servisa.</li>
  <li>Cena ispravnog polovnog ili novog zamenskog telefona je znatno veća od popravke.</li>
</ul>
<p>Popravka je posebno logična kod skupljih iPhone, Samsung Galaxy S/Ultra, Google Pixel i drugih premium modela, pod uslovom da nema dodatnih oštećenja.</p>

<h2>Kada treba razmotriti drugi telefon</h2>
<ul>
  <li>Uređaj je star, spor ili više ne dobija bezbednosna ažuriranja.</li>
  <li>Pored ekrana potrebni su baterija, konektor punjenja ili popravka ploče.</li>
  <li>Ram je jako savijen ili postoji korozija od tečnosti.</li>
  <li>Cena kvalitetnog ekrana približava se vrednosti ispravnog uređaja.</li>
  <li>Telefon ima veoma malu memoriju ili više ne zadovoljava tvoje potrebe.</li>
</ul>
<p>Nemoj računati samo cenu najjeftinijeg oglasa za ekran. Veoma jeftin deo može imati loš prikaz, slab dodir i kratko trajanje, pa se ista popravka plaća ponovo.</p>

<h2>Originalni, reparirani i zamenski ekran</h2>
<h3>Originalni servisni deo</h3>
<p>Najčešće pruža prikaz, osvetljenje, boje i odziv najsličnije fabričkom ekranu. Obično je najskuplja opcija, ali ima najviše smisla kod novog ili skupog telefona.</p>

<h3>Reparirani original</h3>
<p>Originalni panel sa zamenjenim spoljnim staklom može biti dobar odnos cene i kvaliteta ako je reparacija stručno urađena. Pitaj servis šta je tačno menjano i kakvu garanciju dobijaš.</p>

<h3>Kvalitetan zamenski ekran</h3>
<p>Postoje dobre zamenske opcije, ali kvalitet mnogo varira. LCD zamena na telefonu koji je fabrički imao OLED može imati deblji sklop, slabiju crnu boju, veću potrošnju i drugačiji prikaz. Traži da ti servis objasni razliku između ponuđenih klasa, ne samo da kaže „original“ ili „kopija“.</p>

<h2>Pitanja koja treba postaviti servisu</h2>
<ol>
  <li>Da li je cena konačna i uključuje deo, rad i lepak?</li>
  <li>Koji tip i kvalitet ekrana se ugrađuje?</li>
  <li>Kolika je garancija i šta tačno pokriva?</li>
  <li>Da li ostaju True Tone, osvežavanje ekrana, senzor blizine i čitač otiska?</li>
  <li>Da li će telefon posle otvaranja zadržati deklarisanu otpornost na vodu?</li>
  <li>Koliko traje popravka i da li se podaci brišu?</li>
  <li>Da li ću dobiti račun ili potvrdu o izvršenoj usluzi?</li>
</ol>

<h2>Šta proveriti prilikom preuzimanja</h2>
<p>Nemoj otići iz servisa čim se ekran uključi. Testiraj uređaj dok si još tamo:</p>
<ul>
  <li>Pređi prstom preko svih delova ekrana i ukucaj tekst preko cele tastature.</li>
  <li>Proveri belo, crno i sivo polje zbog fleka, mrtvih piksela i probijanja svetla.</li>
  <li>Promeni osvetljenje od minimuma do maksimuma.</li>
  <li>Proveri Face ID/Touch ID, senzor blizine, prednju kameru i razgovorni zvučnik.</li>
  <li>Proveri da ekran ravnomerno naleže i da nema vidljivog lepka ili velikih zazora.</li>
</ul>

<h2>Podaci i priprema pre servisa</h2>
<p>Pre predaje napravi rezervnu kopiju podataka. Zapiši IMEI ili serijski broj, fotografiši stanje kućišta i izvadi SIM karticu ako nije potrebna za test. Isključi šifru samo ako servis to zaista zahteva ili napravi privremenu šifru; nikada ne deli lozinku svog Apple ili Google naloga.</p>
<p>Kod ozbiljnog udara traži kompletnu dijagnostiku, jer oštećenje ekrana može da prati problem sa ramom, baterijom ili konektorima. Ako je baterija naduvena, nemoj pritiskati ekran niti dalje puniti telefon — odmah ga odnesi stručnom servisu.</p>

<h2>Primer odluke</h2>
<p>Ako ispravan polovan telefon vredi 500 €, a kvalitetna zamena ekrana sa garancijom košta 140 €, popravka je uglavnom razumna ako je baterija dobra i nema drugih kvarova. Ako telefon vredi 130 €, ekran košta 90 €, baterija je slaba i konektor punjenja prekida, ulaganje verovatno nema ekonomskog smisla.</p>

<h2>Kratak zaključak</h2>
<p>Zamena ekrana se isplati kada je ostatak uređaja pouzdan, deo odgovarajućeg kvaliteta i ukupna cena popravke ostaje razumna u odnosu na vrednost telefona. Ne biraj servis samo po najnižoj ceni: važni su kvalitet dela, iskustvo, jasna garancija i mogućnost da uređaj testiraš pri preuzimanju.</p>
<p>Uporedi ponude među <a href="/oglasi/servis">servisnim oglasima</a> i pronađi lokalne firme u <a href="/servisi">imeniku servisa</a>. Ako razmatraš kupovinu drugog uređaja, pogledaj aktuelne <a href="/oglasi/telefoni">oglase za telefone</a> i uporedi cenu sa kompletnim troškom popravke.</p>
HTML,
    ];
}

function upgradeDefaultGuides(array $rows): array
{
    $content = defaultGuideContent();
    $changed = false;
    foreach ($rows as &$row) {
        $slug = (string)($row['slug'] ?? '');
        if (!isset($content[$slug])) {
            continue;
        }
        // Nadogradi samo naše kratke početne tekstove; ne prepisuj sadržaj koji je admin već proširio.
        if ((int)($row['content_version'] ?? 1) >= 2 || mb_strlen(strip_tags((string)($row['body_html'] ?? ''))) >= 1200) {
            continue;
        }
        $row['body_html'] = $content[$slug];
        $row['content_version'] = 2;
        $row['updated_at'] = date('Y-m-d H:i:s');
        $changed = true;
    }
    unset($row);

    if ($changed) {
        writeJsonFile('guides.json', $rows);
    }
    return $rows;
}

function defaultGuidesSeed(): array
{
    $now = date('Y-m-d H:i:s');
    $content = defaultGuideContent();
    return [
        [
            'id' => 1,
            'title' => 'Kako proveriti polovan iPhone pre kupovine',
            'slug' => 'provera-polovnog-iphone-a',
            'excerpt' => 'Brza check-lista za bateriju, Face ID, mrežu i istoriju uređaja.',
            'body_html' => $content['provera-polovnog-iphone-a'],
            'content_version' => 2,
            'status' => 'published',
            'seo_title' => 'Provera polovnog iPhone-a: kompletan vodič',
            'seo_description' => 'Na šta da obratiš pažnju pre kupovine polovnog iPhone-a: baterija, Face ID, mreža i originalnost.',
            'og_image' => '',
            'author_id' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'published_at' => $now,
        ],
        [
            'id' => 2,
            'title' => 'Bezbedna kupovina telefona bez prevare',
            'slug' => 'bezbedna-kupovina-telefona',
            'excerpt' => 'Kako da smanjiš rizik kod online kupovine i šta da tražiš od prodavca.',
            'body_html' => $content['bezbedna-kupovina-telefona'],
            'content_version' => 2,
            'status' => 'published',
            'seo_title' => 'Bezbedna kupovina telefona online',
            'seo_description' => 'Praktični saveti za sigurnu kupovinu telefona i izbegavanje prevara.',
            'og_image' => '',
            'author_id' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'published_at' => $now,
        ],
        [
            'id' => 3,
            'title' => 'Kada se isplati zamena ekrana',
            'slug' => 'kada-se-isplati-zamena-ekrana',
            'excerpt' => 'Kako odlučiti da li da menjaš ekran ili da menjaš uređaj.',
            'body_html' => $content['kada-se-isplati-zamena-ekrana'],
            'content_version' => 2,
            'status' => 'published',
            'seo_title' => 'Zamena ekrana telefona: kada ima smisla',
            'seo_description' => 'Vodič za procenu da li je zamena ekrana finansijski isplativa.',
            'og_image' => '',
            'author_id' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'published_at' => $now,
        ],
    ];
}

function getPublishedGuides(): array
{
    $rows = array_values(array_filter(
        getAllGuides(),
        static fn($g) => (string)($g['status'] ?? 'draft') === 'published'
    ));
    usort($rows, static fn($a, $b) => strcmp((string)($b['published_at'] ?? $b['updated_at'] ?? ''), (string)($a['published_at'] ?? $a['updated_at'] ?? '')));
    return $rows;
}

function guideUrl(array $guide): string
{
    $slug = trim((string)($guide['slug'] ?? ''));
    if ($slug === '') {
        return '/vodici';
    }
    return '/vodic/' . rawurlencode($slug);
}

function guidePublishedLabel(array $guide): string
{
    $raw = trim((string)($guide['published_at'] ?? $guide['updated_at'] ?? ''));
    if ($raw === '') {
        return '';
    }
    $ts = strtotime($raw);
    return $ts ? date('d.m.Y.', $ts) : $raw;
}

function getGuideById(int $id): ?array
{
    foreach (getAllGuides() as $guide) {
        if ((int)($guide['id'] ?? 0) === $id) {
            return $guide;
        }
    }
    return null;
}

function getGuideBySlug(string $slug, bool $includeDraft = false): ?array
{
    $slug = listingFacetSlug($slug);
    foreach (getAllGuides() as $guide) {
        if ((string)($guide['slug'] ?? '') !== $slug) {
            continue;
        }
        if (!$includeDraft && (string)($guide['status'] ?? 'draft') !== 'published') {
            return null;
        }
        return $guide;
    }
    return null;
}

function guideSlugTaken(string $slug, int $exceptId = 0): bool
{
    $slug = listingFacetSlug($slug);
    foreach (getAllGuides() as $guide) {
        if ((int)($guide['id'] ?? 0) === $exceptId) {
            continue;
        }
        if ((string)($guide['slug'] ?? '') === $slug) {
            return true;
        }
    }
    return in_array($slug, reservedShopSlugs(), true);
}

function allocateUniqueGuideSlug(string $base, int $exceptId = 0): string
{
    $base = listingFacetSlug($base);
    if ($base === '' || in_array($base, reservedShopSlugs(), true)) {
        $base = 'vodic';
    }
    $candidate = $base;
    $i = 2;
    while (guideSlugTaken($candidate, $exceptId)) {
        $suffix = '-' . $i;
        $trim = 60 - strlen($suffix);
        $candidate = rtrim(substr($base, 0, max(1, $trim)), '-') . $suffix;
        $i++;
        if ($i > 9999) {
            break;
        }
    }
    return $candidate;
}

function saveGuide(array $input, ?int $guideId = null): ?int
{
    $title = trim((string)($input['title'] ?? ''));
    if ($title === '') {
        return null;
    }
    $rows = getAllGuides();

    $status = (string)($input['status'] ?? 'draft');
    if (!in_array($status, ['draft', 'published'], true)) {
        $status = 'draft';
    }

    $slugRaw = trim((string)($input['slug'] ?? ''));
    $slugBase = $slugRaw !== '' ? $slugRaw : slugifyTitle($title);
    $targetId = $guideId ?? 0;
    $slug = allocateUniqueGuideSlug($slugBase, $targetId);

    $payload = [
        'title' => $title,
        'slug' => $slug,
        'excerpt' => trim((string)($input['excerpt'] ?? '')),
        'body_html' => trim((string)($input['body_html'] ?? '')),
        'status' => $status,
        'seo_title' => trim((string)($input['seo_title'] ?? '')),
        'seo_description' => trim((string)($input['seo_description'] ?? '')),
        'og_image' => trim((string)($input['og_image'] ?? '')),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    if ($guideId !== null) {
        foreach ($rows as &$row) {
            if ((int)($row['id'] ?? 0) !== $guideId) {
                continue;
            }
            $payload['id'] = $guideId;
            $payload['created_at'] = (string)($row['created_at'] ?? date('Y-m-d H:i:s'));
            $payload['author_id'] = (int)($row['author_id'] ?? (int)(currentUser()['id'] ?? 0));
            $oldStatus = (string)($row['status'] ?? 'draft');
            $payload['published_at'] = (string)($row['published_at'] ?? '');
            if ($status === 'published' && $oldStatus !== 'published') {
                $payload['published_at'] = date('Y-m-d H:i:s');
            }
            $row = array_merge($row, $payload);
            writeJsonFile('guides.json', $rows);
            return $guideId;
        }
        return null;
    }

    $maxId = 0;
    foreach ($rows as $row) {
        $maxId = max($maxId, (int)($row['id'] ?? 0));
    }
    $newId = $maxId + 1;
    $payload['id'] = $newId;
    $payload['created_at'] = date('Y-m-d H:i:s');
    $payload['author_id'] = (int)(currentUser()['id'] ?? 0);
    $payload['published_at'] = $status === 'published' ? date('Y-m-d H:i:s') : '';
    $rows[] = $payload;
    writeJsonFile('guides.json', $rows);
    return $newId;
}

function deleteGuide(int $guideId): bool
{
    $rows = getAllGuides();
    $before = count($rows);
    $rows = array_values(array_filter($rows, static fn($g) => (int)($g['id'] ?? 0) !== $guideId));
    if (count($rows) === $before) {
        return false;
    }
    writeJsonFile('guides.json', $rows);
    return true;
}
