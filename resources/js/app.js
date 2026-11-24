import './highlight.js';

// De lade is een component van zichzelf; welke kaart erin open staat meldt
// hij hier, zodat het bord die kaart kan markeren zonder zelf te hertekenen.
document.addEventListener('alpine:init', () => {
	// pending is the card that was clicked and is still on its way: its skeleton shows until the panel arrives
	// selected holds the cards picked with Ctrl+click, for the bulk bar
	Alpine.store('board', {
		openCard: null,
		pending: null,
		selected: [],
		toggle(card) {
			this.selected = this.selected.includes(card) ? this.selected.filter((picked) => picked !== card) : [...this.selected, card];
		},
		onlyTodos() {
			return this.selected.length > 0 && this.selected.every((card) => card.startsWith('todo:'));
		},
	});

	window.addEventListener('cards-cleaned-up', () => {
		Alpine.store('board').selected = [];
	});

	window.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && Alpine.store('board').selected.length > 0) {
			Alpine.store('board').selected = [];
		}
	});

	window.addEventListener('drawer-card', (event) => {
		Alpine.store('board').openCard = event.detail.card ?? null;
		Alpine.store('board').pending = null;
	});
});

// Elk verzoek naar de server zet een balk bovenaan, zodat een klik nooit in
// het niets verdwijnt. Livewire meldt begin en eind van elke ronde.
document.addEventListener('livewire:init', () => {
	let running = 0;

	Livewire.hook('request', ({ respond }) => {
		running++;
		document.body.classList.add('is-working');

		respond(() => {
			running = Math.max(0, running - 1);

			if (running === 0) {
				document.body.classList.remove('is-working');
			}
		});
	});
});

// Reloads the page when the session ended, so the login page opens and leads back to the same card and tab.
document.addEventListener('livewire:init', () => {
	Livewire.hook('request', ({ fail }) => {
		fail(({ status, preventDefault }) => {
			if (status === 401 || status === 419) {
				preventDefault();
				window.location.reload();
			}
		});
	});
});

// Closes the logout menu in the sidebar on a click anywhere else.
document.addEventListener('click', (event) => {
	document.querySelectorAll('.side-menu[open]').forEach((menu) => {
		if (! menu.contains(event.target)) {
			menu.removeAttribute('open');
		}
	});
});

// Op een telefoon staat de lade als een blad onderaan het scherm. Die duw je
// naar beneden weg; het kruisje bovenin blijft, maar je hoeft er niet heen.
document.addEventListener('livewire:init', () => {
	const CLOSE_AFTER = 120;
	const FLICK = 0.6;

	let drawer = null;
	let body = null;
	let startY = 0;
	let startedAt = 0;
	let movedBy = 0;
	let dragging = false;

	const isSheet = () => window.matchMedia('(max-width: 720px)').matches;

	const settle = (closing) => {
		drawer.style.transition = 'transform .24s cubic-bezier(.2,.8,.2,1)';
		drawer.style.transform = closing ? 'translateY(100%)' : '';
	};

	// de lade hoort bij het component eromheen, dus die sluit hem ook
	const close = () => {
		const root = drawer.closest('[wire\\:id]');
		const component = root ? Livewire.find(root.getAttribute('wire:id')) : null;

		if (component) {
			component.call('closeDrawer');

			return;
		}

		drawer.style.transform = '';
	};

	document.addEventListener('touchstart', (event) => {
		drawer = null;

		if (! isSheet()) {
			return;
		}

		const sheet = event.target.closest('.drawer');

		// in een veld of op een knop is een veeg geen gebaar maar bediening
		if (! sheet || event.target.closest('input, textarea, select, button, a')) {
			return;
		}

		drawer = sheet;
		body = sheet.querySelector('.dbody');
		startY = event.touches[0].clientY;
		startedAt = event.timeStamp;
		movedBy = 0;
		dragging = false;
	}, { passive: true });

	document.addEventListener('touchmove', (event) => {
		if (drawer === null) {
			return;
		}

		const delta = event.touches[0].clientY - startY;

		// meeslepen begint pas als er niets meer te scrollen valt, anders
		// vecht het gebaar met de inhoud
		if (! dragging && delta > 8 && (body === null || body.scrollTop <= 0)) {
			dragging = true;
		}

		if (! dragging) {
			return;
		}

		movedBy = Math.max(0, delta);
		drawer.style.transition = 'none';
		drawer.style.transform = `translateY(${movedBy}px)`;
	}, { passive: true });

	const release = (event) => {
		if (drawer === null) {
			return;
		}

		if (dragging) {
			const speed = movedBy / Math.max(1, event.timeStamp - startedAt);
			const closing = movedBy > CLOSE_AFTER || speed > FLICK;

			settle(closing);

			if (closing) {
				close();
			}
		}

		drawer = null;
		dragging = false;
	};

	document.addEventListener('touchend', release, { passive: true });
	document.addEventListener('touchcancel', release, { passive: true });
});

// Enter verstuurt het invoervak van de agent; Shift+Enter blijft een nieuwe
// regel. Livewire leest het veld pas bij een wijziging, dus we duwen de waarde
// er zelf in voordat we op de knop drukken.
document.addEventListener('keydown', (event) => {
	if (event.key !== 'Enter' || event.shiftKey || event.altKey || event.ctrlKey || event.metaKey) {
		return;
	}

	const field = event.target.closest('textarea[data-send-on-enter]');

	if (field === null || field.disabled) {
		return;
	}

	const send = field.closest('.composer')?.querySelector('.send');

	if (send === null || send === undefined || send.disabled) {
		return;
	}

	event.preventDefault();
	field.dispatchEvent(new Event('change', { bubbles: true }));
	send.click();
});

// De agent-tab is een gesprek, en een gesprek begin je onderaan: bij het
// laatste antwoord. Dat geldt bij het openen van de tab en bij een binnen-
// gekomen ronde; een verversing zonder nieuw bericht laat je staan waar je
// zat te lezen.
//
// Wanneer het gesprek precies in beeld komt hangt af van de lade, de morph en
// op een telefoon ook van de animatie. In plaats van op al die momenten te
// mikken kijken we gewoon of het er is.
document.addEventListener('livewire:init', () => {
	const LOOK_EVERY = 250;

	let seenExchanges = null;

	// Op een telefoon groeit het gesprek nog terwijl we al kijken, dus eenmaal
	// naar beneden springen is te vroeg. We houden hem even onderaan, en laten
	// los zodra de lezer zelf omhoog scrolt.
	const pinToBottom = () => {
		const until = Date.now() + 2000;

		let placedAt = null;

		const pin = () => {
			const body = document.querySelector('.talk')?.closest('.dbody') ?? null;

			if (body === null || (placedAt !== null && body.scrollTop < placedAt - 2)) {
				return;
			}

			body.scrollTop = body.scrollHeight;
			placedAt = body.scrollTop;

			if (Date.now() < until) {
				requestAnimationFrame(pin);
			}
		};

		pin();
	};

	setInterval(() => {
		const conversation = document.querySelector('.talk');

		// geen gesprek in beeld: de volgende keer telt weer als de eerste
		if (conversation === null) {
			seenExchanges = null;

			return;
		}

		const exchanges = conversation.querySelectorAll('.say').length;

		if (seenExchanges !== null && exchanges <= seenExchanges) {
			return;
		}

		seenExchanges = exchanges;

		pinToBottom();
	}, LOOK_EVERY);
});

// De kop blijft staan bij scrollen en krijgt dan een schaduw, zodat je ziet
// dat er inhoud onder doorloopt.
const markScrolled = () => {
	document.querySelectorAll('.head').forEach((head) => {
		head.classList.toggle('scrolled', window.scrollY > 4);
	});
};

window.addEventListener('scroll', markScrolled, { passive: true });
document.addEventListener('livewire:navigated', markScrolled);

// Een greep aan de rand van een lade of lijst sleep je breder of smaller. De
// breedte staat als variabele op de pagina zelf, zodat een verversing van
// Livewire hem niet weghaalt, en blijft in deze browser bewaard.
(() => {
	const remembered = (name) => {
		try {
			return window.localStorage.getItem('devdock.width.' + name);
		} catch {
			return null;
		}
	};

	const remember = (name, width) => {
		try {
			window.localStorage.setItem('devdock.width.' + name, String(width));
		} catch {
			// zonder opslag geldt de breedte alleen tot je de pagina sluit
		}
	};

	['--diff-width', '--diff-files'].forEach((name) => {
		const width = remembered(name);

		if (width !== null) {
			document.documentElement.style.setProperty(name, width + 'px');
		}
	});

	document.addEventListener('pointerdown', (event) => {
		const grip = event.target.closest('[data-resize]');

		if (grip === null || event.button !== 0) {
			return;
		}

		const panel = grip.dataset.edge === 'start' ? grip.parentElement : grip.previousElementSibling;
		const name = grip.dataset.resize;
		const minimum = Number(grip.dataset.min ?? 120);
		const maximum = Number(grip.dataset.max ?? window.innerWidth - 80);

		event.preventDefault();
		document.documentElement.classList.add('resizing');

		const follow = (moveEvent) => {
			const box = panel.getBoundingClientRect();
			const width = grip.dataset.edge === 'start' ? box.right - moveEvent.clientX : moveEvent.clientX - box.left;
			const clamped = Math.round(Math.min(Math.max(width, minimum), maximum));

			document.documentElement.style.setProperty(name, clamped + 'px');
		};

		const stop = () => {
			document.documentElement.classList.remove('resizing');
			document.removeEventListener('pointermove', follow);
			remember(name, parseInt(getComputedStyle(document.documentElement).getPropertyValue(name), 10) || panel.getBoundingClientRect().width);
		};

		document.addEventListener('pointermove', follow);
		document.addEventListener('pointerup', stop, { once: true });
	});
})();

// A mockup of the agent runs in a frame without access to the page, so it
// tells the page how tall it is and the frame follows.
window.addEventListener('message', (event) => {
	if (typeof event.data?.mockupHeight !== 'number') {
		return;
	}

	for (const frame of document.querySelectorAll('.mockup-frame')) {
		if (frame.contentWindow !== event.source) {
			continue;
		}

		// a chat that stood at the bottom stays there while a frame grows above it
		const scroller = frame.closest('.dbody');
		const atBottom = scroller !== null && scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 120;

		frame.style.height = Math.min(Math.max(event.data.mockupHeight, 80), 2000) + 'px';

		if (atBottom) {
			scroller.scrollTop = scroller.scrollHeight;
		}
	}
});

// Wat je in de codelezer als bekeken aanvinkt, onthoudt deze browser per taak.
// Het vinkje hoort bij de stand van het bestand: verandert het daarna, dan is
// het weer ongezien, zoals op een pull request.
document.addEventListener('alpine:init', () => {
	Alpine.data('viewedFiles', (card, fingerprints) => ({
		key: `devdock.viewed.${card}`,
		seen: {},

		init() {
			try {
				this.seen = JSON.parse(localStorage.getItem(this.key) ?? '{}') ?? {};
			} catch {
				this.seen = {};
			}
		},

		isViewed(file) {
			return fingerprints[file] !== undefined && this.seen[file] === fingerprints[file];
		},

		toggle(file) {
			if (this.isViewed(file)) {
				delete this.seen[file];
			} else {
				this.seen[file] = fingerprints[file];
			}

			try {
				localStorage.setItem(this.key, JSON.stringify(this.seen));
			} catch {
				// a private window keeps nothing; the tick then lasts as long as the page
			}
		},

		viewedCount() {
			return Object.keys(fingerprints).filter((file) => this.isViewed(file)).length;
		},
	}));
});

// Bestanden die op het chatveld vallen, geplakt worden of via de paperclip
// komen, gaan in stukken naar DevDock: Herd neemt geen verzoek boven 2 MB aan.
// Is een bestand heel, dan zet de lade het naast het bericht.
document.addEventListener('alpine:init', () => {
	const chunkBytes = 1.5 * 1024 * 1024;
	const maxBytes = 20 * 1024 * 1024;

	Alpine.data('attachmentDrop', (storeUrl, messages) => ({
		dropping: false,
		uploading: 0,
		failure: null,

		dragEnter(event) {
			if ([...(event.dataTransfer?.types ?? [])].includes('Files')) {
				this.dropping = true;
			}
		},

		drop(event) {
			this.dropping = false;
			this.send([...(event.dataTransfer?.files ?? [])]);
		},

		paste(event) {
			const files = [...(event.clipboardData?.files ?? [])];

			// only a paste with files is ours; plain text goes into the field as usual
			if (files.length > 0) {
				event.preventDefault();
				this.send(files);
			}
		},

		pick(event) {
			this.send([...event.target.files]);
			event.target.value = '';
		},

		async send(files) {
			this.failure = null;

			for (const file of files) {
				if (file.size > maxBytes) {
					this.failure = messages.tooLarge.replace(':name', file.name);

					continue;
				}

				this.uploading++;

				try {
					const upload = [...crypto.getRandomValues(new Uint8Array(16))].map((byte) => byte.toString(16).padStart(2, '0')).join('');
					const chunks = Math.max(1, Math.ceil(file.size / chunkBytes));
					// a pasted screenshot has no name of its own
					const name = file.name && file.name !== 'image.png' ? file.name : `screenshot-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.png`;

					for (let chunk = 0; chunk < chunks; chunk++) {
						const response = await fetch(storeUrl, {
							method: 'POST',
							headers: {
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
								'X-Upload': upload,
								'X-Chunk': String(chunk),
								'X-Chunks': String(chunks),
								'X-File-Name': encodeURIComponent(name),
								'Content-Type': 'application/octet-stream',
								'Accept': 'application/json',
							},
							body: file.slice(chunk * chunkBytes, (chunk + 1) * chunkBytes),
						});

						if (! response.ok) {
							throw new Error((await response.json().catch(() => ({}))).message ?? messages.notArrived.replace(':name', name));
						}
					}

					await this.$wire.addAttachment(upload);
				} catch (error) {
					this.failure = error.message;
				} finally {
					this.uploading--;
				}
			}
		},
	}));
});

// Lets the last click win: a click that only moves to another view cancels a
// move that is still on its way, so the panel never shows the earlier choice
// first. Actions that change something are never cancelled.
const VIEW_ACTIONS = new Set([
	'$set',
	'back',
	'closeContextFile',
	'closeDiff',
	'closeFile',
	'closeDrawer',
	'closeLens',
	'closeLibraryItem',
	'editLens',
	'openContextFile',
	'openDiff',
	'openFavorite',
	'openFile',
	'openLibraryItem',
	'openSetupChecklist',
	'selectProject',
	'setContextScope',
	'setDiffView',
	'setDrawerSubtab',
	'setDrawerTab',
	'showGlobal',
	'showLibrary',
	'showProject',
	'showRule',
	'showSection',
	'showSkill',
]);

const VIEW_EVENTS = new Set(['open-card']);

const movesView = (action) => VIEW_ACTIONS.has(action.name)
	|| (action.name === '__dispatch' && VIEW_EVENTS.has(action.params?.[0]));

document.addEventListener('livewire:init', () => {
	const moving = new Map();

	Livewire.interceptMessage(({ message, onFinish }) => {
		const actions = Array.from(message.actions ?? []);

		if (actions.length === 0 || ! actions.every(movesView)) {
			return;
		}

		moving.set(message.component.id, message);
		onFinish(() => {
			if (moving.get(message.component.id) === message) {
				moving.delete(message.component.id);
			}
		});
	});

	Livewire.interceptAction(({ action }) => {
		if (action.metadata?.type === 'poll' || action.isCancelled()) {
			return;
		}

		const earlier = moving.get(action.component.id);

		if (earlier && ! earlier.isCancelled()) {
			moving.delete(action.component.id);
			earlier.cancel();
		}
	});
});

// Hides a panel the moment you close it; the server catches up behind it.
// Opening a card shows the panel again straight away.
document.addEventListener('livewire:init', () => {
	Livewire.interceptMessage(({ message, onFinish }) => {
		const actions = Array.from(message.actions ?? []);

		if (actions.some((action) => action.name === 'closeDrawer')) {
			onFinish(() => document.body.classList.remove('drawer-closing'));
		}

		if (actions.some((action) => action.name === '__dispatch' && action.params?.[0] === 'open-card')) {
			document.body.classList.remove('drawer-closing');
		}
	});
});

// Shows the outline of the next page the moment you click to it; the page
// itself takes its place once it arrived.
const skeletonFor = (path) => {
	if (path === '/' || path === '') {
		return 'skeleton-board';
	}

	return ['/sessies', '/toegang'].includes(path) ? 'skeleton-page' : 'skeleton-split';
};

// Each page you saw is kept as it looked, so the next visit shows its own
// layout at once, dimmed, instead of a general outline.
const GHOST_KEY = 'devdock.page-ghost.';

const currentPage = () => document.querySelector('.shell > :not(.side):not(.tabbar)');

const keepGhost = () => {
	const page = currentPage();

	if (page === null || page.classList.contains('ghost') || page.classList.contains('sk')) {
		return;
	}

	try {
		sessionStorage.setItem(GHOST_KEY + window.location.pathname, page.outerHTML);
	} catch {
		// a full storage keeps no ghost; the general outline still shows
	}
};

const ghostFor = (path) => {
	let html = null;

	try {
		html = sessionStorage.getItem(GHOST_KEY + path);
	} catch {
		return null;
	}

	if (! html) {
		return null;
	}

	const template = document.createElement('template');
	template.innerHTML = html;
	const ghost = template.content.firstElementChild;

	if (ghost === null) {
		return null;
	}

	// the ghost only shows; nothing in it may start a component or answer a click
	ghost.querySelectorAll('script, .drawer, .scrim, dialog').forEach((element) => element.remove());
	[ghost, ...ghost.querySelectorAll('*')].forEach((element) => {
		[...element.attributes].forEach((attribute) => {
			if (/^(wire:|x-|@|:)/.test(attribute.name)) {
				element.removeAttribute(attribute.name);
			}
		});
	});
	ghost.classList.add('ghost');
	ghost.setAttribute('inert', '');
	ghost.setAttribute('aria-busy', 'true');

	return ghost;
};

document.addEventListener('livewire:navigated', keepGhost);
window.addEventListener('pagehide', keepGhost);

document.addEventListener('livewire:navigate', (event) => {
	const url = new URL(event.detail?.url ?? window.location.href, window.location.origin);
	const page = currentPage();

	if (page === null || url.pathname === window.location.pathname) {
		return;
	}

	keepGhost();

	const ghost = ghostFor(url.pathname);
	const template = document.getElementById(skeletonFor(url.pathname));

	if (ghost !== null) {
		page.replaceWith(ghost);
	} else if (template !== null) {
		page.replaceWith(template.content.firstElementChild.cloneNode(true));
	}

	document.querySelectorAll('.side .nav a, .tabbar a').forEach((link) => {
		new URL(link.href).pathname === url.pathname ? link.setAttribute('aria-current', 'page') : link.removeAttribute('aria-current');
	});
});

// De code-lezer: licht of donker, los van de rest van DevDock en onthouden in
// deze browser, en de boom die meeloopt met het bestand bovenaan in beeld.
document.addEventListener('alpine:init', () => {
	const themeKey = 'devdock.codeTheme';

	Alpine.data('codeReader', () => ({
		theme: null,
		current: null,
		jumping: false,

		init() {
			try {
				this.theme = localStorage.getItem(themeKey);
			} catch {
				this.theme = null;
			}
		},

		isDark() {
			if (this.theme !== null) {
				return this.theme === 'dark';
			}

			const appTheme = document.documentElement.dataset.wbTheme;

			return appTheme === 'dark' || (appTheme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
		},

		switchTheme() {
			this.theme = this.isDark() ? 'light' : 'dark';

			try {
				localStorage.setItem(themeKey, this.theme);
			} catch {
				// a private window keeps nothing; the choice then lasts as long as the page
			}
		},

		// The file whose section reaches the top of the code pane is the one being read.
		followScroll(patch) {
			if (this.jumping) {
				return;
			}

			const top = patch.getBoundingClientRect().top + 24;
			let reading = null;

			for (const section of patch.querySelectorAll(':scope > .dfile')) {
				if (section.getBoundingClientRect().top > top) {
					break;
				}

				reading = section.id;
			}

			this.mark(reading ?? patch.querySelector(':scope > .dfile')?.id ?? null, patch);
		},

		jumpTo(anchor) {
			const section = document.getElementById(anchor);

			if (section === null) {
				return;
			}

			// the chosen file stays marked until you scroll yourself, also when it is
			// one of the last files that never reach the top
			this.jumping = true;
			this.mark(anchor, section.parentElement);
			section.scrollIntoView({ block: 'start', behavior: 'smooth' });
		},

		// Keeps the marked row inside the visible part of the tree.
		mark(anchor, patch) {
			if (anchor === this.current) {
				return;
			}

			this.current = anchor;
			const tree = patch.closest('.diffsplit').querySelector('.difffiles');
			const row = anchor === null ? null : tree.querySelector(`[data-anchor="${CSS.escape(anchor)}"]`);

			if (row === null || row.offsetParent === null) {
				return;
			}

			const treeBox = tree.getBoundingClientRect();
			const rowBox = row.getBoundingClientRect();

			if (rowBox.top < treeBox.top + 8) {
				tree.scrollTop -= treeBox.top + 8 - rowBox.top;
			} else if (rowBox.bottom > treeBox.bottom - 8) {
				tree.scrollTop += rowBox.bottom - treeBox.bottom + 8;
			}
		},
	}));
});

// Een codeblok in de chat, een rapport of een regel: kopiëren met één klik, en
// bij markdown wisselen tussen de code en de opgemaakte tekst. Opgemaakt
// gekopieerd plakt in een editor als koppen, vet en lijsten. Deze browser
// onthoudt welke weergave je koos.
document.addEventListener('alpine:init', () => {
	const viewKey = 'devdock.codeBlockView';

	// Turns the formatted view into plain html an editor understands: real headings, lists and paragraphs.
	const clipboardHtml = (root) => {
		const parts = [];
		let list = null;

		const closeList = () => {
			if (list !== null) {
				parts.push(`<${list.tag}>${list.items.join('')}</${list.tag}>`);
				list = null;
			}
		};

		for (const element of root.querySelectorAll(':scope > .md > *')) {
			const html = element.innerHTML;

			if (element.classList.contains('md-li')) {
				const tag = element.classList.contains('numbered') ? 'ol' : 'ul';

				if (list?.tag !== tag) {
					closeList();
					list = { tag, items: [] };
				}

				list.items.push(`<li>${html}</li>`);

				continue;
			}

			closeList();

			if (element.classList.contains('md-h1')) {
				parts.push(`<h1>${html}</h1>`);
			} else if (element.classList.contains('md-h2')) {
				parts.push(`<h2>${html}</h2>`);
			} else if (element.classList.contains('md-h3')) {
				parts.push(`<h3>${html}</h3>`);
			} else if (element.classList.contains('md-q')) {
				parts.push(`<blockquote>${html}</blockquote>`);
			} else if (element.classList.contains('md-code')) {
				const pre = document.createElement('pre');
				pre.textContent = element.querySelector('.code')?.textContent ?? '';
				parts.push(pre.outerHTML);
			} else {
				parts.push(element.classList.contains('md-p') ? `<p>${html}</p>` : element.outerHTML);
			}
		}

		closeList();

		return parts.join('');
	};

	// Copies through the copy event, which also works on a site served over plain http.
	const copyToClipboard = (text, html = null) => {
		const onCopy = (event) => {
			event.clipboardData.setData('text/plain', text);

			if (html !== null) {
				event.clipboardData.setData('text/html', html);
			}

			event.preventDefault();
		};

		document.addEventListener('copy', onCopy);

		try {
			return document.execCommand('copy');
		} finally {
			document.removeEventListener('copy', onCopy);
		}
	};

	Alpine.data('codeBlock', (isText) => ({
		formatted: false,
		copied: false,

		init() {
			try {
				this.formatted = isText && localStorage.getItem(viewKey) === 'formatted';
			} catch {
				this.formatted = false;
			}
		},

		show(formatted) {
			this.formatted = formatted;

			try {
				localStorage.setItem(viewKey, formatted ? 'formatted' : 'code');
			} catch {
				// a private window keeps nothing; the choice then lasts as long as the page
			}
		},

		copy() {
			const text = this.$refs.raw.textContent;
			const isCopied = copyToClipboard(text, this.formatted ? clipboardHtml(this.$refs.formatted) : null);

			if (isCopied) {
				this.copied = true;
				setTimeout(() => this.copied = false, 1500);
			}
		},
	}));
});

// Een tab die je eerder op deze kaart zag, staat bij de volgende klik meteen
// weer in beeld zoals hij toen was, tot de verse versie binnen is. De kopie
// leeft zolang de pagina open is.
const tabGhosts = new Map();
const TAB_GHOST_LIMIT = 60;

// Takes the copy without ids, so nothing on the page finds an element of it.
const ghostHtml = (elements) => elements.map((element) => element.outerHTML).join('').replace(/\sid="[^"]*"/g, '');

window.keepTabGhost = (drawer) => {
	const card = drawer?.dataset.card;
	const [tab, subtab] = (drawer?.dataset.view ?? '').split(':');

	if (! card || ! tab) {
		return;
	}

	const body = [...drawer.querySelectorAll(':scope > :is(.dbody, .composer, .dfoot):not(.tab-skeleton):not(.tab-ghost)')];
	const subbar = drawer.querySelector(':scope > .subbar');

	// a click on a tab shows its subtabs too; a click on a subtab keeps the bar that stands
	tabGhosts.set(card + ':' + tab, ghostHtml(subbar ? [subbar, ...body] : body));

	if (subtab) {
		tabGhosts.set(card + ':' + tab + ':' + subtab, ghostHtml(body));
	}

	while (tabGhosts.size > TAB_GHOST_LIMIT) {
		tabGhosts.delete(tabGhosts.keys().next().value);
	}
};

window.showTabGhost = (drawer, view) => {
	const ghost = drawer?.querySelector(':scope > .tab-ghost');
	const html = tabGhosts.get(drawer?.dataset.card + ':' + view);

	if (! ghost) {
		return;
	}

	ghost.innerHTML = html ?? '';
	drawer.classList.toggle('has-ghost', html !== undefined);
};
