/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [KIMAI] KimaiDateUtils: responsible for handling date specific tasks
 */

import KimaiPlugin from '../KimaiPlugin';
import { DateTime, Duration, Info } from 'luxon';

export default class KimaiDateUtils extends KimaiPlugin {

    getId()
    {
        return 'date';
    }

    init()
    {
        if (this.getConfigurations().is24Hours()) {
            this.timeFormat = 'HH:mm';
        } else {
            this.timeFormat = 'hh:mm a';
        }
        this.durationFormat = this.getConfiguration('formatDuration');
        this.dateFormat = this.getConfiguration('formatDate');
    }

    _getFormat(format)
    {
        // FIXME replace me once the move from moment to luxon is done
        format = format.replace('DD', 'dd');
        format = format.replace('MM', 'LL');
        format = format.replace('YYYY', 'yyyy');
        return format.replace('A', 'a');
    }

    /**
     * @param {string} format
     * @param {string|Date|null|undefined} dateTime
     * @returns {string}
     */
    format(format, dateTime)
    {
        let newDate = null;


        if (dateTime === null || dateTime === undefined) {
            newDate = DateTime.now();
        } else if (dateTime instanceof Date) {
            newDate = DateTime.fromJSDate(dateTime);
        } else {
            newDate = DateTime.fromISO(dateTime);
        }

        return newDate.toFormat(this._getFormat(format));
    }

    /**
     * @param {string|Date} dateTime
     * @returns {string}
     */
    getFormattedDate(dateTime)
    {
        return this.format(this.dateFormat, dateTime);
    }

    /**
     * @return {Array}
     */
    getWeekDaysShort()
    {
        let weekdays = Info.weekdays('short');
        // the old date-time-range-picker element requires this old order
        // the next line can be removed, once the date-time-range-picker is replaced
        weekdays.unshift(weekdays.pop());

        return weekdays;
    }

    /**
     * @return {Array}
     */
    getMonthNames()
    {
        return Info.months();
    }

    /**
     * Returns a "YYYY-MM-DDTHH:mm:ss" formatted string in local time.
     * This can take Date objects (e.g. from FullCalendar) and turn them into the correct format.
     *
     * @param {Date|DateTime} date
     * @param {boolean|undefined} isUtc
     * @return {string}
     */
    formatForAPI(date, isUtc= false)
    {
        if (date instanceof Date) {
            date = DateTime.fromJSDate(date);
        }

        if (isUtc === undefined || !isUtc) {
            date = date.toUTC();
        }

        return date.toISO({ includeOffset: false, suppressMilliseconds: true });
    }

    /**
     * @param {string} date
     * @param {string} format
     * @return {DateTime}
     */
    fromFormat(date, format)
    {
        return DateTime.fromFormat(date, this._getFormat(format));
    }

    /**
     * @param {string} date
     * @param {string} format
     * @return {boolean}
     */
    isValidDateTime(date, format)
    {
        return this.fromFormat(date, format).isValid;
    }

    /**
     * Adds a string like "00:30:00" or "01:15" to a given date.
     *
     * @param {Date} date
     * @param {string} duration
     * @return {Date}
     */
    addHumanDuration(date, duration)
    {
        /** @type {DateTime} newDate */
        let newDate = null;

        if (date instanceof Date) {
            newDate = DateTime.fromJSDate(date);
        } else if (date instanceof DateTime) {
            newDate = date;
        } else {
            throw 'addHumanDuration() needs a JS Date';
        }

        const parsed = DateTime.fromISO(duration);
        const today = DateTime.now().startOf('day');
        const timeOfDay = parsed.diff(today);

        return newDate.plus(timeOfDay).toJSDate();
    }

    /**
     * @param {string|integer|null} since
     * @return {string}
     */
    formatDuration(since)
    {
        let duration = null;

        if (typeof since === 'string') {
            duration = DateTime.now().diff(DateTime.fromISO(since));
        } else {
            duration = Duration.fromISO('PT' + (since === null ? 0 : since) + 'S');
        }

        return this.formatLuxonDuration(duration);
    }

    /**
     * @param {integer} seconds
     * @return {string}
     */
    formatSeconds(seconds)
    {
        return this.formatLuxonDuration(Duration.fromObject({seconds: seconds}));
    }

    /**
     * @param {Duration} duration
     * @returns {string}
     * @private
     */
    formatLuxonDuration(duration)
    {
        duration = duration.shiftTo('hours', 'minutes', 'seconds');

        return this.formatAsDuration(duration.hours, duration.minutes, duration.seconds);
    }

    /**
     * @param {Date} date
     * @param {boolean|undefined} isUtc
     * @return {string}
     */
    formatTime(date, isUtc= false)
    {
        let newDate = DateTime.fromJSDate(date);

        if (isUtc === undefined || !isUtc) {
            newDate = newDate.toUTC();
        }

        // .utc() is required for calendar
        return newDate.toFormat(this.timeFormat);
    }

    /**
     * @param {int} hours
     * @param {int} minutes
     * @param {int} seconds
     * @return {string}
     */
    formatAsDuration(hours, minutes, seconds)
    {
        if (hours < 0 || minutes < 0 || seconds < 0) {
            return '?';
        }

        return this.durationFormat.replace('%h', (hours < 10 ? '0' + hours : hours)).replace('%m', ('0' + minutes).slice(-2)).replace('%s', ('0' + seconds).slice(-2));
    }

    /**
     * @param {string} duration
     * @returns {int}
     */
    getSecondsFromDurationString(duration)
    {
        const luxonDuration = this.parseDuration(duration);

        if (luxonDuration === null || !luxonDuration.isValid) {
            return 0;
        }

        return luxonDuration.as('seconds');
    }

    /**
     * @param {string} duration
     * @returns {Duration}
     */
    parseDuration(duration)
    {
        if (duration === undefined || duration === null || duration === '') {
            return new Duration({seconds: 0});
        }

        duration = duration.trim().toUpperCase();
        let luxonDuration = null;

        if (duration.indexOf(':') !== -1) {
            const [, hours, minutes, seconds] = duration.match(/(\d+):(\d+)(?::(\d+))*/);
            luxonDuration = Duration.fromObject({hours: hours, minutes: minutes, seconds: seconds});
        } else if (duration.indexOf('.') !== -1 || duration.indexOf(',') !== -1) {
            duration = duration.replace(/,/, '.');
            duration = (parseFloat(duration) * 3600).toString();
            luxonDuration = Duration.fromISO('PT' + duration + 'S');
        } else if (duration.indexOf('H') !== -1 || duration.indexOf('M') !== -1 || duration.indexOf('S') !== -1) {
            /* D for days does not work, because 'PT1H' but with days 'P1D' is used */
            luxonDuration = Duration.fromISO('PT' + duration);
        } else {
            let c = parseInt(duration);
            const d = parseInt(duration).toFixed();
            if (!isNaN(c) && duration === d) {
                duration = (c * 3600).toString();
                luxonDuration = Duration.fromISO('PT' + duration + 'S');
            }
        }

        if (luxonDuration === null || !luxonDuration.isValid) {
            return new Duration({seconds: 0});
        }

        return luxonDuration;
    }

    getFormDateRangeList()
    {
        const TRANSLATE = this.getTranslation();

        const now = DateTime.now();
        const yesterday = now.minus({days: 1});
        const lastWeek = now.minus({week: 1});
        const lastMonth = now.minus({month: 1});
        const lastYear = now.minus({year: 1});

        let rangesList = {};
        rangesList[TRANSLATE.get('yesterday')] = [yesterday.toJSDate(), yesterday.toJSDate()];

        // sunday = 0
        if (this.getConfigurations().getFirstDayOfWeek(false) === 0) {
            rangesList[TRANSLATE.get('thisWeek')] = [now.startOf('week').minus({days: 1}).toJSDate(), now.endOf('week').minus({days: 1}).toJSDate()];
            rangesList[TRANSLATE.get('lastWeek')] = [lastWeek.startOf('week').minus({days: 1}).toJSDate(), lastWeek.endOf('week').minus({days: 1}).toJSDate()];
        } else {
            rangesList[TRANSLATE.get('thisWeek')] = [now.startOf('week').toJSDate(), now.endOf('week').toJSDate()];
            rangesList[TRANSLATE.get('lastWeek')] = [lastWeek.startOf('week').toJSDate(), lastWeek.endOf('week').toJSDate()];
        }

        rangesList[TRANSLATE.get('thisMonth')] = [now.startOf('month').toJSDate(), now.endOf('month').toJSDate()];
        rangesList[TRANSLATE.get('lastMonth')] = [lastMonth.startOf('month').toJSDate(), lastMonth.endOf('month').toJSDate()];
        rangesList[TRANSLATE.get('thisYear')] = [now.startOf('year').toJSDate(), now.endOf('year').toJSDate()];
        rangesList[TRANSLATE.get('lastYear')] = [lastYear.startOf('year').toJSDate(), lastYear.endOf('year').toJSDate()];

        return rangesList;
    }

}
