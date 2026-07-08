/**
 * Single source of truth for Executive pill colours across the purchase pipeline
 * pages (Forecast Analysis, To Order Analysis, MFRG In Progress).
 *
 * ExecColors.get(name) returns { bg, text } for a given executive name. The mapping
 * is deterministic (hash of the normalized name → palette slot), so the SAME exec
 * always gets the SAME colour on every page. A blank/unassigned name returns the
 * neutral "NA" grey.
 *
 * A few historical execs keep their original colours for continuity.
 */
(function (global) {
    'use strict';

    // Distinct, readable palette. `text` is chosen for contrast against `bg`.
    var PALETTE = [
        { bg: '#3b82f6', text: '#fff' }, // blue
        { bg: '#10b981', text: '#fff' }, // emerald
        { bg: '#8b5cf6', text: '#fff' }, // violet
        { bg: '#f59e0b', text: '#111' }, // amber
        { bg: '#ec4899', text: '#fff' }, // pink
        { bg: '#14b8a6', text: '#fff' }, // teal
        { bg: '#ef4444', text: '#fff' }, // red
        { bg: '#6366f1', text: '#fff' }, // indigo
        { bg: '#0ea5e9', text: '#fff' }, // sky
        { bg: '#84cc16', text: '#111' }, // lime
        { bg: '#f97316', text: '#fff' }, // orange
        { bg: '#06b6d4', text: '#111' }, // cyan
        { bg: '#a855f7', text: '#fff' }, // purple
        { bg: '#22c55e', text: '#111' }, // green
        { bg: '#eab308', text: '#111' }, // yellow
        { bg: '#d946ef', text: '#fff' }, // fuchsia
        { bg: '#f43f5e', text: '#fff' }, // rose
        { bg: '#0d9488', text: '#fff' }, // dark teal
        { bg: '#7c3aed', text: '#fff' }, // dark violet
        { bg: '#2563eb', text: '#fff' }, // dark blue
        { bg: '#65a30d', text: '#fff' }, // dark lime
        { bg: '#c026d3', text: '#fff' }, // dark fuchsia
        { bg: '#ea580c', text: '#fff' }, // dark orange
        { bg: '#0891b2', text: '#fff' }, // dark cyan
        { bg: '#be123c', text: '#fff' }, // dark rose
        { bg: '#4f46e5', text: '#fff' }, // dark indigo
        { bg: '#059669', text: '#fff' }, // dark emerald
        { bg: '#9333ea', text: '#fff' }  // dark purple
    ];

    var NEUTRAL = { bg: '#e5e7eb', text: '#6b7280' };

    // Preserve the original colours for the legacy short names.
    var FIXED = {
        'atin':   { bg: '#3b82f6', text: '#fff' },
        'jack':   { bg: '#10b981', text: '#fff' },
        'nitish': { bg: '#8b5cf6', text: '#fff' },
        'ajay':   { bg: '#f59e0b', text: '#111' },
        'candy':  { bg: '#ec4899', text: '#fff' },
        'sruti':  { bg: '#14b8a6', text: '#fff' }
    };

    function normalize(name) {
        return String(name == null ? '' : name).trim().toLowerCase().replace(/\s+/g, ' ');
    }

    function hashString(s) {
        var h = 0;
        for (var i = 0; i < s.length; i++) {
            h = ((h << 5) - h) + s.charCodeAt(i);
            h |= 0; // 32-bit int
        }
        return Math.abs(h);
    }

    function get(name) {
        var key = normalize(name);
        if (key === '') {
            return NEUTRAL;
        }
        if (FIXED[key]) {
            return FIXED[key];
        }
        return PALETTE[hashString(key) % PALETTE.length];
    }

    global.ExecColors = { get: get, neutral: NEUTRAL };
})(typeof window !== 'undefined' ? window : this);
