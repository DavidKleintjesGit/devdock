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
