import { DateTime } from 'luxon';

// Compteur global pour les IDs uniques d'événements
// Initialisé côté PHP dans create.blade.php
let nextEventId = window.ResEvents.length;
let eventsSlots = [];
const t = window.translations || {};
const Status = Object.freeze({
    UNSET: "unset",
    FREE: "free",
    BUSY: "busy",
    PAST: "past",
    TOO_CLOSE: "too-close",
    TOO_FAR: "too-far",
    INVALID: "invalid",
    OVERLAP: "overlap",
    NON_BOOKABLE: "non-bookable",
    label(type) {
        switch (type) {
            case this.UNSET:
                return t.empty || 'Empty';
            case this.FREE:
                return t.available || 'Available';
            case this.BUSY:
                return t.occupied || 'Occupied';
            case this.PAST:
                return t.past || 'Past';
            case this.TOO_CLOSE:
                return t.too_close || 'Too close';
            case this.TOO_FAR:
                return t.too_far || 'Too far';
            case this.INVALID:
                return t.invalid || 'Invalid';
            case this.OVERLAP:
                return t.overlap || 'Overlap';
            case this.NON_BOOKABLE:
                return t.non_bookable || 'Non-bookable';
            default:
                return null;
        }
    }
});

function currency(amount) {
    return new Intl.NumberFormat(window.RoomConfig.settings.locale,{
        style: 'currency',
        currency: window.RoomConfig.settings.currency,
    }).format(amount);
}

function removeEvent(ev) {
    const index = window.ResEvents.indexOf(ev);
    window.ResEvents[index].eventRow.remove();
    if (index !== -1) {
        window.ResEvents.splice(index, 1);
    }
    updateTotalCost();
}

function addEvent() {
    const newEventId = nextEventId++;
    // Cloner le template
    const template = document.getElementById('new-event-row');
    const clone = template.content.cloneNode(true);

    const eventRow = clone.querySelector('.event-row');
    eventRow.setAttribute('data-event-id', newEventId);

    // Remplacer dans tous les attributs contenant __INDEX__
    const elementsWithIndex = eventRow.querySelectorAll('[name*="__INDEX__"], [id*="__INDEX__"], [for*="__INDEX__"]');

    elementsWithIndex.forEach(element => {
        if (element.hasAttribute('name')) {
            element.setAttribute('name', element.getAttribute('name').replace(/__INDEX__/g, String(newEventId)));
        }
        if (element.hasAttribute('id')) {
            element.setAttribute('id', element.getAttribute('id').replace(/__INDEX__/g, String(newEventId)));
        }
        if (element.hasAttribute('for')) {
            element.setAttribute('for', element.getAttribute('for').replace(/__INDEX__/g, String(newEventId)));
        }
    });

    document.getElementById('events-container').appendChild(eventRow);

    // Ajouter aux tableaux
    const ev = {
        id: newEventId,
        start: '',
        end: '',
        uid: '',
        options: [],
        status: Status.UNSET,
        price: 0,
        eventRow: eventRow,
    };
    window.ResEvents.push(ev);

    // Attacher le listener de suppression
    const removeBtn = eventRow.querySelector('.event-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', () => removeEvent(ev));
    }
}

function linkDOMToArrays() {
    window.ResEvents.forEach((ev) => {
        ev.eventRow = document.querySelector(`[data-event-id="${ev.id}"]`);
    });
    window.RoomConfig.discounts.forEach((disc) => {
        disc.discountInput = document.getElementById("discount_" + disc.id);
        disc.discountSummary = document.getElementById("discount_" + disc.id + "-cost");
    })
}

// Initialiser les listeners pour ajouter/supprimer des événements
function initAddRemoveEvent() {
    window.ResEvents.forEach((ev) => {
        document.querySelector("#event-remove-" + ev.id).addEventListener('click', () => removeEvent(ev));
    });

    const addEventBtn = document.getElementById('add-event');
    if (addEventBtn) {
        addEventBtn.addEventListener('click', addEvent);
    }
}

function roomTzDate(str) {
    return DateTime.fromISO(str,{zone:window.RoomConfig.settings.timeZone})
}
function updateAvailability(ev) {
    const start = ev.eventRow.querySelector('.event-start').value;
    ev.start = start ? roomTzDate(start) : '';
    const end = ev.eventRow.querySelector('.event-end').value;
    ev.end = end ? roomTzDate(end) : '';

    const now = DateTime.now();
    if (!ev.start || !ev.end) {
        ev.status = Status.UNSET;
    } else if (ev.end < ev.start) {
        ev.status = Status.INVALID;
    } else if (now > ev.start) {
        ev.status = Status.PAST;
    } else if (window.RoomConfig.settings.reservation_cutoff_days &&
        ev.start - now < window.RoomConfig.settings.reservation_cutoff_days * 1000 * 3600 * 24) {
        ev.status = Status.TOO_CLOSE;
    } else if (window.RoomConfig.settings.reservation_advance_limit &&
        ev.start - now > window.RoomConfig.settings.reservation_advance_limit * 1000 * 3600 * 24) {
        ev.status = Status.TOO_FAR;
    } else if (hasOverlapWithOtherEvents(ev)) {
        ev.status = Status.OVERLAP;
    } else if (!window.IsAdmin && isNonBookable(ev)) {
        ev.status = Status.NON_BOOKABLE;
    } else {
        ev.status = checkAvailability(ev.start,ev.end,ev.uid) ? Status.FREE : Status.BUSY;
    }
    const span = document.querySelector("#event-status-" + ev.id);
    span.textContent = Status.label(ev.status);
    span.classList.remove(...span.classList);
    span.classList.add("status-label", "status-" + ev.status);
}

function checkAvailability(startDateTime, endDateTime, self_uid=null) {
    let conflict;
    conflict = eventsSlots.some(function(event, index) {
        return (
            (startDateTime < event.end && endDateTime > event.start && (self_uid ? event.uid != self_uid : true)) // Chevauchement
        );
    });
    return !conflict; // Retourne true si disponible, false sinon
}

function hasOverlapWithOtherEvents(currentEv) {
    // Check if current event overlaps with any other event in the same reservation
    return window.ResEvents.some(function(otherEv) {
        if (otherEv.id === currentEv.id) {
            return false; // Skip self
        }
        if (!otherEv.start || !otherEv.end) {
            return false; // Skip events without dates
        }
        // Check overlap: start1 < end2 AND end1 > start2
        return currentEv.start < otherEv.end && currentEv.end > otherEv.start;
    });
}

function isNonBookable(ev) {
    const settings = window.RoomConfig.settings;

    // Check if event spans multiple days
    const isMultiDay = !ev.start.hasSame(ev.end, 'day');

    // Time range check - multi-day events are not allowed if time restrictions exist
    if ((settings.day_start_time || settings.day_end_time) && isMultiDay) {
        return true;
    }

    // Time range check for single-day events
    if (settings.day_start_time) {
        const startTime = ev.start.toFormat('HH:mm');
        if (startTime < settings.day_start_time) return true;
    }
    if (settings.day_end_time) {
        const endTime = ev.end.toFormat('HH:mm');
        if (endTime > settings.day_end_time) return true;
    }

    // Weekday check - check ALL days between start and end
    let current = ev.start.startOf('day');
    const endDay = ev.end.startOf('day');

    while (current <= endDay) {
        const day = String(current.weekday); // Luxon: 1=Mon, 7=Sun
        if (!settings.allowed_weekdays.includes(day)) return true;
        current = current.plus({ days: 1 });
    }

    // Unavailability check
    const unavailabilities = window.RoomConfig.unavailabilities || [];
    for (const u of unavailabilities) {
        const uStart = roomTzDate(u.start);
        const uEnd = roomTzDate(u.end);
        if (ev.start < uEnd && ev.end > uStart) return true;
    }

    // Fenêtres de disponibilité par jour (La Pépite) : restriction seule.
    // Le créneau doit tenir entièrement dans une fenêtre du bon jour.
    const windows = settings.availability_windows || [];
    if (windows.length > 0) {
        if (isMultiDay) return true;
        const toMin = (s) => { const [h, m] = String(s).split(':').map(Number); return h * 60 + m; };
        const wd = ev.start.weekday; // 1..7
        const startMin = ev.start.hour * 60 + ev.start.minute;
        const endMin = ev.end.hour * 60 + ev.end.minute;
        const fits = windows.some(w => w.weekday === wd && endMin > startMin && startMin >= toMin(w.start) && endMin <= toMin(w.end));
        if (!fits) return true;
    }

    return false;
}
async function initAvailabilityCheck() {
    try {
        const response = await fetch(window.RoomConfig.settings.availability_route);
        const data = await response.json();

        // Store full calendar events data globally for calendar display
        window.calendarEventsData = data;
        // Handle both old format (array) and new format (object with events property)
        const eventsData = data.events || data;
        eventsSlots = eventsData.map(slot => ({
            start: roomTzDate(slot.start),
            end: roomTzDate(slot.end),
            uid: slot.uid
        }));
    } catch (error) {
        console.error('Error loading calendar:', error);
    }
}

function updateOption(ev,optionId,checked) {
    if (checked) {
        if (!ev.options.includes(optionId)) {
            ev.options.push(optionId);
        }
    } else {
        const index = ev.options.indexOf(optionId);
        if (index !== -1) {
            ev.options.splice(index, 1);
        }
    }
}
function splitByDay(start, end, ALLOW_LATE_END) {
    const segments = [];

    const startDay = start.startOf('day');
    const endDay = end.startOf('day');

    // Vérifier si la réservation traverse minuit
    const crossesMidnight = !startDay.equals(endDay);

    // Extraire les heures avec décimales
    const startHour = start.hour + start.minute / 60;
    const endHour = end.hour + end.minute / 60;

    // Vérifier si on a une continuation tardive autorisée
    const hasLateContinuation =
        crossesMidnight &&
        endHour <= ALLOW_LATE_END;

    // Itérer sur chaque jour
    let current = startDay;

    while (current <= endDay) {
        const isFirst = current.equals(startDay);
        const isLast = current.equals(endDay);

        segments.push({
            start: isFirst ? startHour : 0,
            end: isLast ? endHour : 24,
            date: current.toLocaleString(),
        });

        current = current.plus({ days: 1 });
    }

    // Gérer la continuation tardive
    if (hasLateContinuation) {
        segments.pop();
        segments[segments.length - 1].is_last = true;
        segments[segments.length - 1].end = 24 + endHour;
    }

    return segments;
}
function pepTimeToHours(str) {
    if (!str) return 0;
    const p = String(str).split(':');
    return (parseInt(p[0], 10) || 0) + ((parseInt(p[1], 10) || 0)) / 60;
}

// La Pépite : contexte tarifaire (statut du réservant). Pour un connecté il est
// figé (compte) ; pour un invité il est piloté par sa déclaration dans le form.
function pepContext() {
    const s = window.RoomConfig.settings;
    if (!s.is_guest) {
        return { orgType: s.fixed_org_type, isMember: !!s.fixed_is_member };
    }
    const org = document.querySelector('input[name="org_type"]:checked')?.value || 'for_profit';
    const isMember = !!document.getElementById('pep_is_member')?.checked;
    return { orgType: org, isMember };
}

// Grille de prix active selon le contexte (non-lucratif → price_np, sinon lucratif).
function pepPrices() {
    const s = window.RoomConfig.settings;
    const np = pepContext().orgType === 'non_profit';
    const pick = (m) => np ? ((s.prices_np?.[m] ?? s.prices_lp?.[m])) : (s.prices_lp?.[m]);
    return { hourly: pick('hourly'), half_day: pick('half_day'), full_day: pick('full_day') };
}

// La Pépite : aperçu de prix par CRÉNEAU (miroir du moteur serveur PricingService).
// Chaque segment est classé selon les fenêtres globales, facturé au prix de la salle.
function getEventPrice(start, end) {
    const s = window.RoomConfig.settings;
    const late_end_hour = s.allow_late_end_hour;
    const P = pepPrices();
    const priceHourly = P.hourly;
    const priceHalf = P.half_day;
    const priceFull = P.full_day;
    const hourlyMax = s.hourly_max_hours;

    const mS = pepTimeToHours(s.half_day_morning_start), mE = pepTimeToHours(s.half_day_morning_end);
    const aS = pepTimeToHours(s.half_day_afternoon_start), aE = pepTimeToHours(s.half_day_afternoon_end);
    const eS = pepTimeToHours(s.half_day_evening_start), eE = pepTimeToHours(s.half_day_evening_end);
    const fS = pepTimeToHours(s.full_day_start), fE = pepTimeToHours(s.full_day_end);
    const match = (a, b) => Math.abs(a - b) < 0.02;

    const L = {
        full: t.full_day_booking || 'Journée complète',
        morning: t.morning_half_day || 'Demi-journée matin',
        afternoon: t.afternoon_half_day || 'Demi-journée après-midi',
        evening: t.evening_half_day || 'Demi-journée soir',
        hourly: t.hourly_booking || "Réservation à l'heure",
    };

    const segments = splitByDay(start, end, late_end_hour);
    let price = 0;
    let hourlyMinutes = 0; // minutes au tarif horaire (pour l'heure offerte membre)
    const parts = [];

    segments.forEach((seg) => {
        const a = seg.start, b = seg.end, dur = b - a;
        if (priceFull != null && match(a, fS) && match(b, fE)) {
            price += priceFull; parts.push(L.full);
        } else if (priceHalf != null && match(a, mS) && match(b, mE)) {
            price += priceHalf; parts.push(L.morning);
        } else if (priceHalf != null && match(a, aS) && match(b, aE)) {
            price += priceHalf; parts.push(L.afternoon);
        } else if (priceHalf != null && match(a, eS) && match(b, eE)) {
            price += priceHalf; parts.push(L.evening);
        } else if (priceHourly != null && dur <= hourlyMax + 0.001) {
            const h = Math.round(dur * 100) / 100;
            price += priceHourly * h; hourlyMinutes += dur * 60; parts.push(L.hourly + ' (' + h + 'h)');
        } else if (priceFull != null) {
            price += priceFull; parts.push(L.full);
        }
    });

    // Libellé agrégé
    const counts = {};
    parts.forEach((p) => { counts[p] = (counts[p] || 0) + 1; });
    const labelParts = Object.keys(counts).map((k) => counts[k] > 1 ? counts[k] + '× ' + k : k);
    const dateStr = segments.length
        ? (segments.length > 1
            ? segments[0].date + ' ' + (t.to || 'to') + ' ' + segments[segments.length - 1].date
            : segments[0].date)
        : '';
    const label = dateStr + ' (' + labelParts.join(', ') + ')';
    return [label, price, hourlyMinutes];
}

function getOptionsPrice(options) {
    let label = "";
    let price = 0;
    let index;
    let count = 0;
    options.forEach((optionId) => {
        index = window.RoomConfig.options.findIndex((opt) => opt.id == optionId)
        if (index >= 0) {
            label += window.RoomConfig.options[index].name + ", ";
            price += window.RoomConfig.options[index].price;
            count++;
        }
    });
    label = label.substring(0, label.length - 2);
    if (count === 0) {
        return ['', 0];
    } else if (count === 1) {
        label = "option: " + label;
    } else {
        label = "options: " + label;
    }
    return ([label, price]);
}

function updateCost(ev) {
    // Allow cost calculation for FREE status, or for soft statuses when admin
    const softStatuses = [Status.PAST, Status.TOO_CLOSE, Status.TOO_FAR, Status.NON_BOOKABLE];
    const canCalculateCost = ev.status === Status.FREE ||
        (window.IsAdmin && softStatuses.includes(ev.status));

    if (!canCalculateCost) {
        ev.price = 0;
        ev.hourlyMinutes = 0;
        ev.eventRow.querySelector(".event-info-text").textContent = "";
        return;
    }
    const [label, price, hourlyMinutes] = getEventPrice(ev.start, ev.end);
    const [options_label, options_price] = getOptionsPrice(ev.options);
    ev.price = price + options_price;
    ev.hourlyMinutes = hourlyMinutes || 0;
    let full_label = options_label ? label + " - " + options_label : label;
    full_label += ": " + currency(ev.price);
    ev.eventRow.querySelector(".event-info-text").textContent = full_label;
}

function getDiscountValue(disc,initPrice) {
    return disc.type == "fixed" ? disc.value : disc.type == "percent" ? disc.value*initPrice/100 : 0;
}

function updateTotalCost() {
    let initPrice = 0;
    window.ResEvents.map((ev) => initPrice += ev.price);
    document.getElementById("total-cost").textContent = currency(initPrice);
    let sumDiscounts = 0, current;
    window.RoomConfig.discounts.forEach((disc) => {
        if (window.EnabledDiscounts.includes(disc.id)) {
            current = getDiscountValue(disc,initPrice);
            sumDiscounts += current;
            disc.discountSummary.textContent = currency(-current);
            showDOM(disc.discountSummary.parentElement.parentElement);
        } else {
            hideDOM(disc.discountSummary.parentElement.parentElement);
        }
    });

    const special_discount = Math.abs(parseFloat(document.getElementById("special_discount")?.value));
    const special_discount_cost_span = document.getElementById("special_discount-cost");
    if (special_discount) {
        special_discount_cost_span.textContent = currency(-special_discount);
        showDOM(special_discount_cost_span.parentElement.parentElement);
    } else {
        hideDOM(special_discount_cost_span.parentElement.parentElement);
    }

    const donation = Math.abs(parseFloat(document.getElementById("donation")?.value));
    const donation_cost_span = document.getElementById("donation-cost");
    if (donation) {
        donation_cost_span.textContent = currency(donation);
        showDOM(donation_cost_span.parentElement.parentElement);
    } else {
        hideDOM(donation_cost_span.parentElement.parentElement);
    }

    const settings = window.RoomConfig.settings;
    const ctx = pepContext();
    const memberPct = ctx.isMember ? (settings.member_discount_percent || 0) : 0;

    // Heure offerte membre (quota mensuel) : gratuité horaire avant la remise -10 %.
    const freeAvail = ctx.isMember ? (settings.member_free_minutes_remaining || 0) : 0;
    const hourlyRate = pepPrices().hourly;
    const totalHourlyMin = window.ResEvents.reduce((sum, ev) => sum + (ev.hourlyMinutes || 0), 0);
    const free_cost_span = document.getElementById("pep-free-cost");
    let freeAmount = 0;
    if (memberPct > 0 && freeAvail > 0 && hourlyRate && totalHourlyMin > 0) {
        const freeMin = Math.min(60, freeAvail, totalHourlyMin);
        freeAmount = Math.round(hourlyRate * freeMin / 60 * 100) / 100;
    }
    if (free_cost_span) {
        if (freeAmount > 0) {
            free_cost_span.textContent = currency(-freeAmount);
            showDOM(free_cost_span.parentElement.parentElement);
        } else {
            hideDOM(free_cost_span.parentElement.parentElement);
        }
    }

    // Remise membre La Pépite (-10 %) sur le prix restant (après heure offerte).
    const member_cost_span = document.getElementById("pep-member-cost");
    let memberDiscount = 0;
    const memberBase = initPrice - freeAmount;
    if (memberPct > 0 && memberBase > 0) {
        memberDiscount = Math.round(memberBase * memberPct) / 100;
        if (member_cost_span) {
            member_cost_span.textContent = currency(-memberDiscount);
            showDOM(member_cost_span.parentElement.parentElement);
        }
    } else if (member_cost_span) {
        hideDOM(member_cost_span.parentElement.parentElement);
    }

    const final_cost = initPrice - sumDiscounts - freeAmount - memberDiscount - (special_discount || 0) + (donation || 0);
    document.getElementById("final-cost").textContent = currency(final_cost);
}

function initUpdateDiscounts() {
    window.RoomConfig.discounts.forEach((disc) => {
        disc.discountInput.addEventListener('change', () => {
            if (disc.discountInput.checked && !window.EnabledDiscounts.includes(disc.id)) {
                window.EnabledDiscounts.push(disc.id);
                updateTotalCost();
            } else if (!disc.discountInput.checked && window.EnabledDiscounts.includes(disc.id)) {
                window.EnabledDiscounts.splice(window.EnabledDiscounts.indexOf(disc.id), 1);
                updateTotalCost();
            }
        });
        if (disc.discountInput.checked && !window.EnabledDiscounts.includes(disc.id)) {
            window.EnabledDiscounts.push(disc.id);
        } else if (!disc.discountInput.checked && window.EnabledDiscounts.includes(disc.id)) {
            window.EnabledDiscounts.splice(window.EnabledDiscounts.indexOf(disc.id), 1);
        }
    });
}

function initUpdateRow() {
    document.getElementById('events-container').addEventListener('input', (event) => {
        const target = event.target;
        if (target.matches('.event-start,.event-end')) {
            const id = target.closest('.event-row').getAttribute("data-event-id");
            const ev = window.ResEvents.find(e => e.id == id);
            updateAvailability(ev);
            updateCost(ev);
            updateTotalCost();
        } else if (target.matches('.event-row-options input')) {
            const id = target.closest('.event-row').getAttribute("data-event-id");
            const ev = window.ResEvents.find(e => e.id == id);
            const optionId = parseInt(target.getAttribute("value"));
            updateOption(ev,optionId,target.checked);
            updateCost(ev);
            updateTotalCost();
        }
    });
}

function fillContactInfo() {
    const id = parseInt(document.getElementById("contact-select").value);
    const contact = window.Contacts.find(contact => contact.id == id) ?? null;
    if (contact) {
        document.getElementById("type_" + contact.type).checked = true;
        dataShowWhen(true, contact.type);
    }
    const entity_name = contact?.entity_name ? contact.entity_name : "";
    document.getElementById("entity_name").value = entity_name;
    const first_name = contact?.first_name ? contact.first_name : "";
    document.getElementById("first_name").value = first_name;
    const last_name = contact?.last_name ? contact.last_name : "";
    document.getElementById("last_name").value = last_name;
    const email = contact?.email ? contact.email : "";
    document.getElementById("email").value = email;
    const invoice_email = contact?.invoice_email ? contact.invoice_email : "";
    document.getElementById("invoice_email").value = invoice_email;
    const invoice_email_field = document.querySelector('[data-toggle="invoice-email"]');
    const has_invoice_email = document.getElementById("has_invoice_email");
    invoice_email ? (has_invoice_email.checked = true) : (has_invoice_email.checked = false);
    invoice_email ? showDOM(invoice_email_field) : hideDOM(invoice_email_field);
    const phone = contact?.phone ? contact.phone : "";
    document.getElementById("phone").value = phone;
    const street = contact?.street ? contact.street : "";
    document.getElementById("street").value = street;
    const zip = contact?.zip ? contact.zip : "";
    document.getElementById("zip").value = zip;
    const city = contact?.city ? contact.city : "";
    document.getElementById("city").value = city;
}

function initFillContactInfo() {
    const contactSelect = document.getElementById("contact-select");
    if (contactSelect) {
        contactSelect.addEventListener('change', () => {
            fillContactInfo();
            updateTotalCost();
        });
        fillContactInfo();
    }
}

function initUpdateSpecial() {
    const donationInput = document.getElementById("donation");
    if (donationInput) {
        donationInput.addEventListener('input', () => {
            updateTotalCost();
        });
    }
    const specialDiscountInput = document.getElementById("special_discount");
    if (specialDiscountInput) {
        specialDiscountInput.addEventListener('input', () => {
            updateTotalCost();
        });
    }
}
function hideDOM(elem) {
    if (elem && !elem.classList.contains("hidden")) {
        elem.classList.add("hidden");
    }
}

function showDOM(elem) {
    if (elem?.classList.contains("hidden")) {
        elem.classList.remove("hidden");
    }
}
function showDiscountsFor(type) {
    let nb_shown = 0;
    window.RoomConfig.discounts.forEach((discount) => {
        if (discount.restrict_to && discount.restrict_to != type) {
            hideDOM(discount.discountInput.parentElement);
            if (window.EnabledDiscounts.includes(discount.id)) {
                window.EnabledDiscounts.splice(window.EnabledDiscounts.indexOf(discount.id), 1);
                discount.discountInput.checked = false;
            }
        } else if (discount.restrict_to && discount.restrict_to == type) {
            showDOM(discount.discountInput.parentElement);
            nb_shown++;
        } else {
            nb_shown++;
        }
    });
    const discounts_group = document.getElementById("discounts-form-group");
    nb_shown > 0 ? showDOM(discounts_group) : hideDOM(discounts_group);
}

function dataShowWhen(show, type) {
    if (show) {
        // Masquer tous les éléments avec data-show-when
        document.querySelectorAll('[data-show-when]').forEach((elem) => {
            hideDOM(elem);
        });

        // Afficher les éléments correspondant au type sélectionné
        document.querySelectorAll(`[data-show-when="${type}"]`).forEach((elem) => {
            showDOM(elem);
        });
        showDiscountsFor(type);
        document.getElementById("entity_name").required = (type == "organization");
    }
}

function initDataShowWhen() {
    const types = ["individual", "organization"];
    types.forEach((type) => {
        const radioBtn = document.getElementById("type_" + type);
        if (radioBtn) {
            radioBtn.addEventListener('change', (event) => {
                dataShowWhen(radioBtn.checked, type);
                updateTotalCost();
            });
            dataShowWhen(radioBtn.checked, type);
        }
    });
    const invoiceEmailCheckbox = document.getElementById("has_invoice_email");
    if (invoiceEmailCheckbox) {
        const input = document.querySelector('[data-toggle="invoice-email"]');
        invoiceEmailCheckbox.addEventListener('change', () => {
            invoiceEmailCheckbox.checked ? showDOM(input) : hideDOM(input);
        });
        invoiceEmailCheckbox.checked ? showDOM(input) : hideDOM(input);
    }
}

function validateEventsBeforeSubmit(event) {
    if (window.ResEvents.length === 0) {
        event.preventDefault();
        alert(t.error_no_dates || 'Error: You must add at least one reservation date.');
        return false;
    }

    // Hard blocking statuses - always block submission
    const blockingStatuses = [Status.UNSET, Status.INVALID, Status.BUSY, Status.OVERLAP];
    // Soft statuses - only block for non-admins
    const softStatuses = [Status.PAST, Status.TOO_CLOSE, Status.TOO_FAR, Status.NON_BOOKABLE];

    const hardInvalid = window.ResEvents.filter(ev => blockingStatuses.includes(ev.status));
    const softInvalid = window.ResEvents.filter(ev => softStatuses.includes(ev.status));

    if (hardInvalid.length > 0) {
        event.preventDefault();

        let errorMessage = (t.error_invalid_dates || 'Error: Some reservation dates are not valid:') + '\n\n';
        hardInvalid.forEach(ev => {
            const statusLabel = Status.label(ev.status);
            errorMessage += `- ${statusLabel}\n`;
        });
        errorMessage += '\n' + (t.error_fix_dates || 'Please fix these dates before submitting the form.');

        alert(errorMessage);
        return false;
    }

    if (!window.IsAdmin && softInvalid.length > 0) {
        event.preventDefault();

        let errorMessage = (t.error_invalid_dates || 'Error: Some reservation dates are not valid:') + '\n\n';
        softInvalid.forEach(ev => {
            const statusLabel = Status.label(ev.status);
            errorMessage += `- ${statusLabel}\n`;
        });
        errorMessage += '\n' + (t.error_fix_dates || 'Please fix these dates before submitting the form.');

        alert(errorMessage);
        return false;
    }

    return true;
}

function showLoaderModal() {
    const modal = document.getElementById('loader-modal');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function hideLoaderModal() {
    const modal = document.getElementById('loader-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function initFormValidation() {
    const form = document.querySelector('form.reservation-form');
    if (form) {
        form.addEventListener('submit', function(event) {
            // First validate events
            if (!validateEventsBeforeSubmit(event)) {
                return false;
            }
            // If validation passed, show loader
            showLoaderModal();
            return true;
        });
    }
}

// La Pépite : quand un invité déclare son statut (org / membre), on recalcule
// tous les prix en direct.
function initPepDeclaration() {
    const recompute = () => {
        window.ResEvents.forEach((ev) => updateCost(ev));
        updateTotalCost();
    };
    document.querySelectorAll('input[name="org_type"]').forEach((r) => r.addEventListener('change', recompute));
    const mem = document.getElementById('pep_is_member');
    if (mem) mem.addEventListener('change', recompute);
}

// Export for use in cancel modal
window.showLoaderModal = showLoaderModal;
window.hideLoaderModal = hideLoaderModal;

document.addEventListener('DOMContentLoaded', () => {
    window.ResEvents.forEach((ev) => {
        ev.start = ev.start ? roomTzDate(ev.start) : '';
        ev.end = ev.end ? roomTzDate(ev.end) : '';
    })
    linkDOMToArrays(); // Provide links to DOM elements in events and discounts arrays
    initDataShowWhen(); // Setup conditional form fields (only for individuals / organization / etc.)
    initAddRemoveEvent();
    initUpdateRow(); // Add event listeners to row form elements
    initUpdateDiscounts(); // Add event listeners to row form elements AND do an initial update
    initUpdateSpecial(); // Add event listeners for donation and special discount
    initFillContactInfo();
    initPepDeclaration(); // La Pépite : recalcul live selon la déclaration invité
    initFormValidation(); // Validate events before form submission
    initAvailabilityCheck().then(function() { // Download calendar slots
        window.ResEvents.forEach((ev) => { // for each event, update corresponding arrays, then update cost
            updateAvailability(ev);
            window.RoomConfig.options.forEach((opt) => {
                updateOption(ev,opt.id,ev.eventRow.querySelector("#option_" + ev.id + "_" + opt.id).checked);
            });
            updateCost(ev);
        });
        updateTotalCost();
    });
});
