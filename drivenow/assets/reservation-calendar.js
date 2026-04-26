(function (global) {
    function parseISODate(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }

        var parts = value.split('-');
        if (parts.length !== 3) {
            return null;
        }

        var year = Number(parts[0]);
        var month = Number(parts[1]) - 1;
        var day = Number(parts[2]);

        if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) {
            return null;
        }

        var date = new Date(Date.UTC(year, month, day));
        if (
            date.getUTCFullYear() !== year ||
            date.getUTCMonth() !== month ||
            date.getUTCDate() !== day
        ) {
            return null;
        }

        return date;
    }

    function formatISODate(date) {
        var year = date.getUTCFullYear();
        var month = String(date.getUTCMonth() + 1).padStart(2, '0');
        var day = String(date.getUTCDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function addDays(date, days) {
        var next = new Date(date.getTime());
        next.setUTCDate(next.getUTCDate() + days);
        return next;
    }

    function buildDateSet(ranges) {
        var set = new Set();

        (ranges || []).forEach(function (range) {
            var start = parseISODate(range.inicio);
            var end = parseISODate(range.fim);

            if (!start || !end || end < start) {
                return;
            }

            for (var cursor = start; cursor <= end; cursor = addDays(cursor, 1)) {
                set.add(formatISODate(cursor));
            }
        });

        return set;
    }

    function monthLabel(year, monthIndex) {
        var labelDate = new Date(Date.UTC(year, monthIndex, 1));
        return labelDate.toLocaleDateString('pt-BR', {
            month: 'long',
            year: 'numeric',
            timeZone: 'UTC',
        });
    }

    function dayShortLabels() {
        return ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
    }

    function createReservationCalendar(options) {
        var container = options.container;
        var startInput = options.startInput || null;
        var endInput = options.endInput || null;
        var feedbackElement = options.feedbackElement || null;
        var reservedRanges = options.reservedRanges || [];
        var blockedRanges = options.blockedRanges || [];

        var reservedSet = buildDateSet(reservedRanges);
        var blockedSet = buildDateSet(blockedRanges);

        var today = new Date();
        var todayUtc = new Date(Date.UTC(today.getFullYear(), today.getMonth(), today.getDate()));
        var viewedMonthDate = new Date(Date.UTC(todayUtc.getUTCFullYear(), todayUtc.getUTCMonth(), 1));

        function getSelectedRange() {
            var startDate = startInput ? parseISODate(startInput.value) : null;
            var endDate = endInput ? parseISODate(endInput.value) : null;

            if (!startDate || !endDate) {
                return null;
            }

            if (endDate <= startDate) {
                return null;
            }

            return {
                start: startDate,
                end: endDate,
            };
        }

        function selectionContains(isoDate) {
            var range = getSelectedRange();
            if (!range) {
                return false;
            }

            var date = parseISODate(isoDate);
            if (!date) {
                return false;
            }

            return date >= range.start && date <= range.end;
        }

        function getDateState(isoDate) {
            if (blockedSet.has(isoDate)) {
                return 'blocked';
            }

            if (reservedSet.has(isoDate)) {
                return 'reserved';
            }

            return 'available';
        }

        function clearFeedback() {
            if (!feedbackElement) {
                return;
            }

            feedbackElement.classList.add('hidden');
            feedbackElement.textContent = '';
        }

        function showFeedback(message) {
            if (!feedbackElement) {
                return;
            }

            feedbackElement.textContent = message;
            feedbackElement.classList.remove('hidden');
        }

        function validateSelectedRange() {
            if (!startInput || !endInput || !startInput.value || !endInput.value) {
                clearFeedback();
                return true;
            }

            var startDate = parseISODate(startInput.value);
            var endDate = parseISODate(endInput.value);

            if (!startDate || !endDate || endDate <= startDate) {
                showFeedback('A data de devolucao deve ser posterior a data de reserva.');
                return false;
            }

            for (var cursor = startDate; cursor <= endDate; cursor = addDays(cursor, 1)) {
                var iso = formatISODate(cursor);
                if (reservedSet.has(iso) || blockedSet.has(iso)) {
                    showFeedback('O periodo selecionado possui datas indisponiveis. Escolha outro intervalo.');
                    return false;
                }
            }

            clearFeedback();
            return true;
        }

        function handleDateClick(isoDate) {
            if (!startInput || !endInput) {
                return;
            }

            if (getDateState(isoDate) !== 'available') {
                return;
            }

            if (!startInput.value || (startInput.value && endInput.value)) {
                startInput.value = isoDate;
                endInput.value = '';
                clearFeedback();
            } else {
                if (isoDate <= startInput.value) {
                    startInput.value = isoDate;
                    endInput.value = '';
                    clearFeedback();
                } else {
                    endInput.value = isoDate;
                }
            }

            if (endInput.value && !validateSelectedRange()) {
                endInput.value = '';
            }

            render();
        }

        function renderMonth(year, monthIndex, showTitle) {
            var wrapper = document.createElement('div');
            wrapper.className = 'rounded-2xl border subtle-border bg-white/5 p-3';

            if (showTitle !== false) {
                var title = document.createElement('h4');
                title.className = 'text-sm font-semibold text-white mb-3 capitalize';
                title.textContent = monthLabel(year, monthIndex);
                wrapper.appendChild(title);
            }

            var weekHeader = document.createElement('div');
            weekHeader.className = 'grid grid-cols-7 gap-1 mb-2';
            dayShortLabels().forEach(function (name) {
                var item = document.createElement('span');
                item.className = 'text-[11px] text-white/60 text-center';
                item.textContent = name;
                weekHeader.appendChild(item);
            });
            wrapper.appendChild(weekHeader);

            var grid = document.createElement('div');
            grid.className = 'grid grid-cols-7 gap-1';

            var firstDay = new Date(Date.UTC(year, monthIndex, 1));
            var firstWeekDay = firstDay.getUTCDay();
            var daysInMonth = new Date(Date.UTC(year, monthIndex + 1, 0)).getUTCDate();

            for (var blank = 0; blank < firstWeekDay; blank += 1) {
                var blankCell = document.createElement('span');
                blankCell.className = 'h-9 rounded-lg';
                grid.appendChild(blankCell);
            }

            for (var day = 1; day <= daysInMonth; day += 1) {
                var date = new Date(Date.UTC(year, monthIndex, day));
                var isoDate = formatISODate(date);
                var state = getDateState(isoDate);
                var inSelection = selectionContains(isoDate);
                var isPast = date < todayUtc;

                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'h-9 rounded-lg text-xs font-medium border transition-colors';
                button.textContent = String(day);

                if (isPast) {
                    button.disabled = true;
                    button.className += ' border-white/5 text-white/30 bg-white/5 cursor-not-allowed';
                } else if (state === 'reserved') {
                    button.className += ' border-red-400/40 bg-red-500/25 text-red-100 cursor-not-allowed';
                    button.disabled = true;
                    button.title = 'Data reservada';
                } else if (state === 'blocked') {
                    button.className += ' border-amber-400/50 bg-amber-400/25 text-amber-100 cursor-not-allowed';
                    button.disabled = true;
                    button.title = 'Data bloqueada pelo proprietario';
                } else {
                    button.className += ' border-emerald-400/40 bg-emerald-500/20 text-emerald-100 hover:bg-emerald-500/30';
                    button.addEventListener('click', function (clickedDate) {
                        return function () {
                            handleDateClick(clickedDate);
                        };
                    }(isoDate));
                }

                if (inSelection && !isPast && state === 'available') {
                    button.className = 'h-9 rounded-lg text-xs font-semibold border border-indigo-300/50 bg-indigo-500/45 text-white';
                }

                grid.appendChild(button);
            }

            wrapper.appendChild(grid);
            return wrapper;
        }

        function shiftViewedMonth(delta) {
            viewedMonthDate = new Date(
                Date.UTC(
                    viewedMonthDate.getUTCFullYear(),
                    viewedMonthDate.getUTCMonth() + delta,
                    1
                )
            );
            render();
        }

        function render() {
            if (!container) {
                return;
            }

            container.innerHTML = '';

            var controls = document.createElement('div');
            controls.className = 'flex items-center justify-between mb-3';

            var prevButton = document.createElement('button');
            prevButton.type = 'button';
            prevButton.className = 'h-9 w-9 rounded-lg border subtle-border bg-white/10 text-white hover:bg-white/20 transition-colors text-lg leading-none';
            prevButton.setAttribute('aria-label', 'Mes anterior');
            prevButton.textContent = '‹';
            prevButton.addEventListener('click', function () {
                shiftViewedMonth(-1);
            });

            var currentMonthLabel = document.createElement('h4');
            currentMonthLabel.className = 'text-sm font-semibold text-white capitalize';
            currentMonthLabel.textContent = monthLabel(
                viewedMonthDate.getUTCFullYear(),
                viewedMonthDate.getUTCMonth()
            );

            var nextButton = document.createElement('button');
            nextButton.type = 'button';
            nextButton.className = 'h-9 w-9 rounded-lg border subtle-border bg-white/10 text-white hover:bg-white/20 transition-colors text-lg leading-none';
            nextButton.setAttribute('aria-label', 'Proximo mes');
            nextButton.textContent = '›';
            nextButton.addEventListener('click', function () {
                shiftViewedMonth(1);
            });

            controls.appendChild(prevButton);
            controls.appendChild(currentMonthLabel);
            controls.appendChild(nextButton);
            container.appendChild(controls);

            var monthView = renderMonth(
                viewedMonthDate.getUTCFullYear(),
                viewedMonthDate.getUTCMonth(),
                false
            );

            container.appendChild(monthView);
        }

        function bindInputs() {
            if (!startInput || !endInput) {
                return;
            }

            var minDate = formatISODate(todayUtc);
            startInput.min = minDate;
            endInput.min = minDate;

            startInput.addEventListener('change', function () {
                if (startInput.value) {
                    endInput.min = startInput.value;
                } else {
                    endInput.min = minDate;
                }

                if (endInput.value && !validateSelectedRange()) {
                    endInput.value = '';
                }

                render();
            });

            endInput.addEventListener('change', function () {
                if (!validateSelectedRange()) {
                    endInput.value = '';
                }
                render();
            });
        }

        bindInputs();
        render();

        return {
            refresh: function (nextPayload) {
                reservedRanges = (nextPayload && nextPayload.reservados) || [];
                blockedRanges = (nextPayload && nextPayload.bloqueados) || [];
                reservedSet = buildDateSet(reservedRanges);
                blockedSet = buildDateSet(blockedRanges);
                render();
            },
            validateRange: validateSelectedRange,
        };
    }

    global.createReservationCalendar = createReservationCalendar;
})(window);
