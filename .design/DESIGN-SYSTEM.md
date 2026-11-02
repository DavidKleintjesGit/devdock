# Ontwerptaal van DevDock

> Het ontwerpcanvas is leidend: wat daar onder **Bouwstenen** staat, staat hier in code. Alle stijl zit in
> één bestand, [`resources/css/app.css`](../resources/css/app.css). Bouw je iets nieuws, kijk dan eerst
> of een bouwsteen hieronder het al doet.

## De regels

### 1. Een pagina vult het hele scherm

De schil is een zijbalk links en de pagina rechts. De kop (`head`) blijft staan; alleen de inhoud
schuift. Details komen nooit op een eigen pagina maar als **lade** van rechts (`drawer`), zodat je
altijd ziet waar je vandaan kwam.

### 2. Status heet naar wat hij betekent

Een kaart, rij of label krijgt zijn stand van een enum, nooit een kleur van de aanroeper. De enum
geeft de toon (`tone()`, `dotTone()`), de view zet hem alleen neer.

| Bouwsteen | Standen |
| --- | --- |
| `sdot` (stip op een kaart) | `yours` jouw beurt · `busy` er loopt iets · `quiet` wacht · `red` vast |
| `ldot` (stip van een lens) | `pass` · `fail` · `busy` |
| `label` (rechthoekig, zes hoeken) | `green` · `amber` · `red` · `teal` · `blue` · `muted` |
| `path` (toestemming) | standaard mag · `ask` vraagt eerst · `deny` nooit · `skip` doet niet mee |

### 3. Een rij die ergens heen gaat, is zelf de knop

Een regel met één bestemming (een taak, sessie, bestand, bevinding) is als geheel klikbaar:
`button.row` of `a.row`, nooit een losse knop "openen" aan het eind. Wat ín de rij iets anders doet
(een vinkje, ontkoppelen) houdt zijn eigen klik.

### 4. Niets verdwijnt op een smal scherm

Een onderdeel verandert van vorm, het wordt niet weggelaten.

- onder **1180 px** wordt de zijbalk smaller en de rand van de pagina kleiner;
- onder **900 px** wordt de zijbalk een tabbalk onderaan (`tabbar`), staan de bordkolommen naast
  elkaar om doorheen te vegen, en wordt een lijst-met-pagina (`split`) eerst de lijst en dan de pagina
  met een terugknop.

### 5. Elke klik laat zien dat er iets gebeurt

Bij elk Livewire-verzoek loopt de laadbalk bovenaan (`body.is-working`). Een knop die lang kan duren is
een `<x-busy-button>`.

## Tokens

Kleuren, schaduwen en lettertypes staan als custom properties op `:root`, met een donkere set onder
`data-wb-theme="dark"` en `"system"`. Gebruik altijd een token, nooit een losse hexwaarde.

| Groep | Tokens |
| --- | --- |
| Vlakken | `--bg` `--surface` `--surface-2` `--sunken` `--column` |
| Lijnen | `--line` `--line-strong` `--faint` |
| Tekst | `--fg` `--fg-2` `--muted` |
| Accent | `--accent` `--accent-fg` `--accent-soft` `--accent-ink` `--accent-light` `--accent-wash` |
| Signaal | `--red*` `--amber*` `--green*` `--blue*`, elk met `-soft` (achtergrond) en waar nodig `-dot` |
| Code | `--code-bg` `--code-fg` |
| Diepte | `--shadow` `--shadow-head` `--shadow-lg` `--shadow-pop` `--scrim` |
| Letter | `--font` (Geist) `--mono` (Geist Mono) |
| Maat | `--side` (zijbalk) `--gutter` (rand van de pagina) |

## Bouwstenen

| Groep | Klassen | Waarvoor |
| --- | --- | --- |
| Schil | `shell` `side` `nav` `side-foot` `tabbar` `brand` `wordmark` `working` | zijbalk, tabbalk op een telefoon, laadbalk |
| Pagina | `main` `head` `head-inner` `head-title` `toolbar` `page` `stack` | kop met titel en filters, inhoud eronder |
| Lijst en pagina | `split` `index` `index-group` | Regels, Skills en Workflows: lijst links, pagina rechts |
| Bediening | `btn` (`primary` `danger` `text` `sm` `xs`) `icon-btn` `fbtn` `seg` `switch` `tick` `pick` `choice(s)` `tchip` | knoppen, filters, keuzes |
| Velden | `field` `input` `textarea` `search` | invoer met label erboven |
| Labels | `label` `count` `badge-n` `chips` `path` | korte standen en tellingen |
| Vlakken | `card` `list` `list-head` `row` `section` `section-head` `empty` `alert` `banner` `notebox` `facts` `kv` | lijsten, secties, meldingen |
| Menu's | `menu` `menu-pop` `picker-*` `popt` | menu achter ⋯ en de projectkiezer |
| Bord | `board-scroll` `cols` `col` `col-head` `kcard` `prio` | kolommen en kaarten van 116 px hoog |
| Lade | `drawer` `dhead` `tabs` `dbody` `dsec` `dfoot` `scrim` | detail van een kaart, sessie, lens of bevinding |
| Laden | `skeleton` `bone` (`line` `block` `bubble` `dot`) `bone-row` `tab-skeleton` `steps-skeleton` | grijze vormen zolang een kaart of tab onderweg is |
| Agent | `chat` `chat-banner` `chat-turn` (`you` `agent`) `answer` `agent-steps` `step-list` `caret` `reportbar` (`green` `amber` `red`) `mockup` `composer` | gesprek met de agent: hele antwoorden, ingeklapte stappen, getekende mockups en antwoordknoppen |
| Sessie | `talk` `say` `turns` `turn` | een gelezen Claude-sessie |
| Werkkopie | `filerow` `commitrow` `dbar` `progress` | gewijzigde bestanden, commit, database |
| Review | `steps` `chain` `loop` `lens-row` `verdict` `finding` `checkpath` | rondes, lenzen en bevindingen |
| Code | `code-block` `diff` `diffsplit` `difffiles` `diffpatch` `md` | diff en gelezen markdown |
| Instellingen | `settings` `settings-rail` `settings-main` `setlist` `set` | venster met een rail links |
| Venster | `dialog-wrap` `dialog` `toast` | vragen en meldingen boven de pagina |

## Blade-componenten

| Component | Doet |
| --- | --- |
| `<x-app-layout>` | de schil: zijbalk, tabbalk, laadbalk |
| `<x-icon name="…" small/>` | een icoon uit `partials/icons` |
| `<x-mark :size="…"/>` | het beeldmerk |
| `<x-project-picker>` | de projectkiezer in de kop |
| `<x-busy-button>` | een knop die laat zien dat hij bezig is |
| `<x-diff>` | een diff, per blok met de gewijzigde regels |
| `<x-mockup :mockup="…" :run="…"/>` | een mockup van de agent in een frame met de stylesheets van het project |

## Namen

Klassen zijn kort en heten naar wat het onderdeel is. Een losse `style=""` in een view is toegestaan
voor een eenmalige plaatsing (een marge, een uitlijning), niet voor een kleur of lettermaat: die komt
uit een token of een bouwsteen. Komt dezelfde inline stijl drie keer terug, dan is het een bouwsteen.
