# Ontwerptaal van DevDock

> Status: **voorstel**. De app draait nog op `resources/css/app.css`; dit is wat we ervoor in de plaats
> willen. Het levende voorbeeld staat in [`mockups/stijl.html`](mockups/stijl.html), de stylesheet in
> [`mockups/stijl.css`](mockups/stijl.css). Lees die twee naast dit document — wat daar niet in staat,
> bestaat niet.

## Waarom

DevDock is één gereedschap met vijf plekken. Je moet je op Sessies net zo thuis voelen als op het
bord, zonder opnieuw te leren hoe een rij werkt. Dat is nu niet zo:

- **Tien soorten rij** doen het werk van vier: `kcard`, `linkrow`, `frow`, `krow`, `srow`, `lrow`,
  `wrow`, `dfile`, `set`, `statusrow`. Elke pagina heeft zijn eigen variant gekregen.
- **Twee woordenschatten voor status.** `dot` praat in kleuren (green, amber, red, teal, gray),
  `ldot` in betekenis (pass, fail, run, wait, skip). Een `chip` krijgt zijn kleur van de aanroeper mee.
- **Vier soorten kop** die hetzelfde doen: `section-head`, `fieldhead`, `panelhead`, `listhead`.
- **Losse maten in de views.** `style="font-size:12.5px"` staat tientallen keren in Blade. Dat is geen
  slordigheid maar een ontbrekende naam.
- **Op een smal scherm verdwijnt er van alles**, zie hieronder.

## De twee regels

### 1. Status heet naar wat hij betekent, nooit naar een kleur

Vijf standen, en meer komen er niet:

| Stand | Wanneer |
| --- | --- |
| `goed` | af, geslaagd, niets meer te doen |
| `bezig` | loopt nu, beweegt uit zichzelf |
| `wacht` | nog niet begonnen, of wacht op iemand |
| `stuk` | mislukt, of het blokkeert iets anders |
| `uit` | telt niet mee, staat er ter informatie |

Drie vormen uit diezelfde woordenschat: `status-dot` (bolletje), `status-chip` (pil met tekst) en
`status-bar` (streepje, voor een reeks naast elkaar zoals de lenzen van een ronde). `note` gebruikt
dezelfde namen voor een melding met een zin erbij.

Een component kiest zijn stand nooit zelf uit een kleur. Een enum geeft `goed`, niet `green`.

### 2. Een rij die ergens heen gaat, is zelf de knop

Een regel in een lijst die één bestemming heeft — een taak, een sessie, een
bestand, een bevinding — is in zijn geheel klikbaar. Er komt geen knop "openen"
of "lezen" aan het eind. Die knop maakt het aanklikgebied kleiner in plaats van
groter, en hij herhaalt wat de rij al is.

Dat geldt op elke pagina, zodat je nergens hoeft te zoeken waar je moet drukken:
een kaart op het bord, een sessie, een gewijzigd bestand, een gevallen lens.

Wat ín die rij staat en iets ánders doet houdt de klik bij zich: een vinkje dat
kiest, een knop die ontkoppelt. Een rij met meerdere bestemmingen — de
koppelingen, bijvoorbeeld, met openen, wijzigen en ontkoppelen naast elkaar — is
geen klikbare rij maar een rij met knoppen.

Elke klikbare rij draagt `role="button"`, een `tabindex` en dezelfde reactie op
muis en toetsenbord.

### 3. Niets verdwijnt op een smal scherm

Een onderdeel verandert van vorm; het wordt nooit weggelaten. Een kolom wordt een stap, een tabel
wordt een lijst, een lijst naast de inhoud wordt een laag eroverheen met een terugknop.

Daarom is elk component geschreven op de breedte van **zijn houder** (`@container`), niet op die van
het venster. Eén markup, twee vormen — geen tweede blok dat alleen op mobiel meedoet.

Wat nu wél verdwijnt onder 900 pixels, en dus terug moet:

- het kolommenmenu op het bord (`menu.desk`) — je kunt op een telefoon niet kiezen wélke kolommen je ziet
- de kolomkop, inclusief waar de kolom op wacht
- de tabelkop en de tabelcellen op Sessies — die staan daarnaast nóg een keer in de markup als `m-meta`
- regels en woorden op de regelspagina (`frow .lines`, `frow .read`)
- onder 720 px: de bestandenlijst in het diff-paneel (`difffiles`), waardoor je op een telefoon geen
  ander bestand kunt kiezen

## De onderdelen

### Vier rijen, meer niet

| Component | Waarvoor | Vervangt |
| --- | --- | --- |
| `card` | een ding op het bord | `kcard` |
| `list-row` | een regel in een lijst: status, naam met subregel, kenmerken, actie | `linkrow`, `krow`, `lrow`, `wrow`, `dfile`, `statusrow` |
| `table-row` | kolommen die je naast elkaar wilt lezen | `srow`, `frow`, `scols`, `fcols`, `m-meta` |
| `setting-row` | uitleg links, één besturing rechts | `set` |

`list-row` heeft altijd dezelfde volgorde: **status · naam met subregel · kenmerken · actie.** In een
smalle houder zakken de kenmerken onder de naam.

`table-row` draagt de kolomnaam mee als `data-kolom`, zodat die op een telefoon vóór de waarde komt
te staan in plaats van te verdwijnen.

### Koppen

Eén `section-header`: titel links, telling of hint rechts. Het niveau bepaalt alleen welke titel erin
staat — `heading` voor een sectie, `eyebrow` voor een veld. Binnen een vlak krijgt hij `inside`.

### De actiebalk

Onderaan elk paneel. Links wat je kunt doen, rechts **altijd hetzelfde soort regel**: waar dit werk
staat (de branch, het pad, het ticket). Nu zegt die regel per tab iets anders — een agentnaam, een
status, een branch — waardoor je hem niet kunt lezen zonder eerst te kijken wat er staat.

### Filters

Twee vormen, met een regel erbij:

- een groep waarin precies één ding aan staat → **segment**
- iets wat je los aan en uit zet → **filterknop** met een telling
- een lijst die te lang is om uit te spreiden → **menu**

Drie vormen door elkaar op één balk, zoals nu, is er één te veel.

## Namen

Namen worden voluit geschreven, ook in CSS. `kdesc`, `ldesc`, `fcols`, `scols` en `m-meta` zeggen de
lezer niets. Een klasse heet naar wat het onderdeel is (`list-row-meta`), niet naar waar het toevallig
voor het eerst stond.

Maten komen uit de tokens. Zes tekstmaten (`--text-micro` tot `--text-page`), afstanden in stappen van
vier (`--space-1` tot `--space-6`). Een losse `px` in een view betekent dat er een token ontbreekt.

## Wat nog niet beslist is

- **De schil.** De linkerbalk klapt onder 1180 px dicht tot iconen en valt onder 900 px helemaal weg
  voor een tabbalk. Dat is nog niet tegen regel 2 gehouden.
- **De lade.** Of een taak een lade over het bord moet zijn, of iets anders, is een routevraag die per
  plek aan bod komt.
- **Donker.** De tokens staan er, maar de donkere kant is nog niet nagelopen op de nieuwe onderdelen.
