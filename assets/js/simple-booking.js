/**
 * Simple Booking frontend enhancements.
 */

const initializeSimpleBooking = (booking) => {
	const form = booking.querySelector('[data-simple-booking-form]');
	const service = booking.querySelector('[data-simple-booking-service]');
	const provider = booking.querySelector('[data-simple-booking-provider]');
	const date = booking.querySelector('[data-simple-booking-date]');
	const time = booking.querySelector('[data-simple-booking-time]');
	const timeStatus = booking.querySelector('[data-simple-booking-time-status]');
	const providerHint = booking.querySelector('[data-simple-booking-provider-hint]');
	const errorMessage = booking.querySelector('[data-simple-booking-messages][data-status="error"]');
	const config = window.simpleBookingConfig;
	let availabilityRequest = null;

	if (errorMessage) {
		errorMessage.focus();
	}

	if (!form || !service || !provider || !date || !time || !config) {
		return;
	}

	const steps = Array.from(form.querySelectorAll('[data-simple-booking-step]'));
	const progress = form.querySelector('[data-simple-booking-progress]');
	const progressSteps = Array.from(form.querySelectorAll('[data-simple-booking-progress-step]'));
	const navigations = Array.from(form.querySelectorAll('[data-simple-booking-navigation]'));
	const backButtons = Array.from(form.querySelectorAll('[data-simple-booking-back]'));
	const nextButtons = Array.from(form.querySelectorAll('[data-simple-booking-next]'));
	const submitButton = form.querySelector('[type="submit"]');
	let currentStep = 1;

	const selectedLabel = (control) => {
		const option = control.options[control.selectedIndex];
		return option && option.value ? option.textContent.trim() : '—';
	};

	const updateReview = () => {
		const firstName = form.elements.simple_booking_first_name?.value.trim() || '';
		const lastName = form.elements.simple_booking_last_name?.value.trim() || '';
		const values = {
			service: selectedLabel(service),
			provider: selectedLabel(provider),
			date: date.value || '—',
			time: selectedLabel(time),
			customer: `${firstName} ${lastName}`.trim() || '—',
			email: form.elements.simple_booking_email?.value.trim() || '—',
			phone: form.elements.simple_booking_phone?.value.trim() || '—',
			notes: form.elements.simple_booking_notes?.value.trim() || '—',
		};

		Object.entries(values).forEach(([key, value]) => {
			const target = form.querySelector(`[data-simple-booking-review="${key}"]`);
			if (target) {
				target.textContent = value;
			}
		});
	};

	const showStep = (stepNumber, focusHeading = true) => {
		currentStep = Math.min(steps.length, Math.max(1, stepNumber));

		steps.forEach((step) => {
			const active = Number(step.dataset.simpleBookingStep) === currentStep;
			step.hidden = !active;
			step.setAttribute('aria-hidden', String(!active));
		});

		progressSteps.forEach((item) => {
			if (Number(item.dataset.simpleBookingProgressStep) === currentStep) {
				item.setAttribute('aria-current', 'step');
			} else {
				item.removeAttribute('aria-current');
			}
		});

		if (3 === currentStep) {
			updateReview();
		}

		if (focusHeading) {
			steps[currentStep - 1]?.querySelector('[data-simple-booking-step-heading]')?.focus();
		}
	};

	const validateStep = (stepNumber) => {
		const step = steps[stepNumber - 1];
		const controls = Array.from(step.querySelectorAll('input, select, textarea'));
		const invalid = controls.find((control) => !control.disabled && !control.checkValidity());

		if (invalid) {
			invalid.reportValidity();
			invalid.focus();
			return false;
		}

		if (1 === stepNumber && (!time.value || time.disabled)) {
			timeStatus.textContent = config.selectFirst;
			time.focus();
			return false;
		}

		return true;
	};

	const resetTimes = (message = config.selectFirst) => {
		time.replaceChildren(new Option(message, ''));
		time.disabled = true;
		timeStatus.textContent = '';
	};

	const loadTimes = async () => {
		if (availabilityRequest) {
			availabilityRequest.abort();
		}
		if (!service.value || !provider.value || !date.value) {
			resetTimes();
			return;
		}

		const request = new AbortController();
		availabilityRequest = request;
		const requestTimer = window.setTimeout(() => request.abort(), 15000);
		resetTimes(config.loadingTimes);
		timeStatus.textContent = config.loadingTimes;

		const body = new URLSearchParams({
			action: config.action,
			nonce: config.nonce,
			service_id: service.value,
			provider_id: provider.value,
			date: date.value,
		});

		try {
			const response = await fetch(config.ajaxUrl, {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body,
				credentials: 'same-origin',
				signal: request.signal,
			});
			const result = await response.json();

			if (!response.ok || !result.success || !Array.isArray(result.data?.slots)) {
				throw new Error('Availability request failed.');
			}

			const slots = result.data.slots;
			time.replaceChildren(new Option(slots.length ? config.chooseTime : config.noTimes, ''));
			slots.forEach((slot) => time.add(new Option(slot.label, slot.value)));
			time.disabled = 0 === slots.length;
			timeStatus.textContent = result.data.message || '';
		} catch (error) {
			if (error.name === 'AbortError' && availabilityRequest !== request) {
				return;
			}

			resetTimes(config.loadError);
			timeStatus.textContent = config.loadError;
		} finally {
			window.clearTimeout(requestTimer);
			if (availabilityRequest === request) {
				availabilityRequest = null;
			}
		}
	};

	const updateProviders = () => {
		const selectedService = service.value;
		let availableProviders = 0;

		Array.from(provider.options).forEach((option, index) => {
			if (0 === index) {
				return;
			}

			const services = (option.dataset.services || '').split(',').filter(Boolean);
			const available = Boolean(selectedService) && services.includes(selectedService);
			option.hidden = !available;
			option.disabled = !available;
			availableProviders += available ? 1 : 0;
		});

		const selectedOption = provider.options[provider.selectedIndex];
		if (selectedOption && selectedOption.disabled) {
			provider.value = '';
		}

		provider.disabled = !selectedService || 0 === availableProviders;
		providerHint.textContent = selectedService && 0 === availableProviders
			? booking.dataset.noProviders
			: booking.dataset.providerPrompt;
		providerHint.hidden = Boolean(selectedService) && availableProviders > 0;
		loadTimes();
	};

	booking.classList.add('simple-booking--enhanced');
	progress.hidden = false;
	navigations.forEach((navigation) => {
		navigation.hidden = false;
	});
	backButtons.forEach((button) => {
		button.hidden = false;
		button.addEventListener('click', () => showStep(currentStep - 1));
	});
	nextButtons.forEach((button) => {
		button.addEventListener('click', () => {
			if (validateStep(currentStep)) {
				showStep(currentStep + 1);
			}
		});
	});

	service.addEventListener('change', updateProviders);
	provider.addEventListener('change', loadTimes);
	date.addEventListener('change', loadTimes);
	form.addEventListener('submit', (event) => {
		const appointmentValid = validateStep(1);
		const customerValid = appointmentValid ? validateStep(2) : false;

		if (!appointmentValid || !customerValid) {
			event.preventDefault();
			showStep(appointmentValid ? 2 : 1, false);
			return;
		}

		if (submitButton) {
			submitButton.disabled = true;
			submitButton.setAttribute('aria-disabled', 'true');
		}
	});

	updateProviders();
	const invalidStep = form.querySelector('[aria-invalid="true"]')?.closest('[data-simple-booking-step]');
	showStep(invalidStep ? Number(invalidStep.dataset.simpleBookingStep) : 1, false);
};

document.querySelectorAll('[data-simple-booking]').forEach(initializeSimpleBooking);
