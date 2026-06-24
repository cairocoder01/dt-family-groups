/* global dtFamilyGroups, dtFamilyGroupType */
(function () {
    'use strict';

    var container = document.getElementById('dt-family-tree-container');
    if (!container || typeof dtFamilyGroups === 'undefined') {
        return;
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'className') {
                    node.className = attrs[k];
                } else if (k === 'style') {
                    node.setAttribute('style', attrs[k]);
                } else {
                    node.setAttribute(k, attrs[k]);
                }
            });
        }
        (children || []).forEach(function (c) {
            if (typeof c === 'string') {
                node.appendChild(document.createTextNode(c));
            } else if (c) {
                node.appendChild(c);
            }
        });
        return node;
    }

    function msg(text) {
        container.innerHTML = '';
        container.appendChild(el('p', { className: 'dt-family-tree-msg' }, [text]));
    }

    // ── render a single person card ────────────────────────────────────────────

    function personCard(member) {
        var statusEl = null;
        if (member.marital_status) {
            statusEl = el('span', {
                className: 'dt-person-status',
                style: member.marital_color ? 'background:' + member.marital_color : '',
            }, [member.marital_status]);
        }

        var nameEl = member.post_url
            ? el('a', { href: member.post_url }, [member.name])
            : el('span', {}, [member.name]);

        var genderEl = member.gender
            ? el('span', { className: 'dt-person-gender' }, [member.gender])
            : null;

        return el('div', { className: 'dt-family-person' }, [nameEl, genderEl, statusEl]);
    }

    // ── build a "couple / solo" unit with optional children row ───────────────

    function buildUnit(primaryId, spouseId, childIds, memberMap) {
        var coupleEl = el('div', { className: 'dt-family-couple' }, [
            personCard(memberMap[primaryId]),
            spouseId ? el('span', { className: 'dt-family-spouse-join', title: 'Married' }, ['⚭']) : null,
            spouseId ? personCard(memberMap[spouseId]) : null,
        ]);

        var unitChildren = [];
        unitChildren.push(coupleEl);

        if (childIds.length) {
            var childCards = childIds.map(function (cid) {
                return personCard(memberMap[cid]);
            });
            unitChildren.push(el('div', { className: 'dt-family-children-row' }, childCards));
        }

        return el('div', { className: 'dt-family-unit' }, unitChildren);
    }

    // ── main render function ───────────────────────────────────────────────────

    function render(data) {
        var members   = data.members || [];
        var groupType = typeof dtFamilyGroupType !== 'undefined' ? dtFamilyGroupType : data.group_type;

        if (groupType !== 'family') {
            msg(dtFamilyGroups.i18n.not_family);
            return;
        }

        if (!members.length) {
            msg(dtFamilyGroups.i18n.no_relations);
            return;
        }

        // Index members by ID.
        var memberMap = {};
        members.forEach(function (m) { memberMap[m.ID] = m; });

        var memberIds = members.map(function (m) { return m.ID; });

        // Determine whether any family relationships exist.
        var hasRelations = members.some(function (m) {
            return m.spouse_ids.length || m.parent_ids.length || m.children_ids.length;
        });
        if (!hasRelations) {
            msg(dtFamilyGroups.i18n.no_relations);
            return;
        }

        // ── generational BFS ──────────────────────────────────────────────────
        // A "generation" is a set of member IDs at the same depth.
        // Roots = members with no in-group parents.
        var placed = {};           // ID → true once assigned to a generation

        var roots = memberIds.filter(function (id) {
            return !memberMap[id].parent_ids.some(function (pid) { return memberMap[pid]; });
        });

        // Dedup: if a root is already a spouse of another root, remove them.
        var rootSet = {};
        roots.forEach(function (id) { rootSet[id] = true; });
        var rootsDeduped = roots.filter(function (id) {
            if (placed[id]) { return false; }
            placed[id] = true;
            // Mark spouses in this generation as placed too.
            memberMap[id].spouse_ids.forEach(function (sid) {
                if (rootSet[sid]) { placed[sid] = true; }
            });
            return true;
        });

        // If nothing qualifies as a root (cyclic / disconnected), use all members.
        if (!rootsDeduped.length) {
            rootsDeduped = memberIds.slice(0, 1);
            placed[rootsDeduped[0]] = true;
        }

        var generations = [];
        var currentGen  = rootsDeduped.slice();

        while (currentGen.length) {
            generations.push(currentGen.slice());

            var nextGen = [];
            currentGen.forEach(function (id) {
                memberMap[id].children_ids.forEach(function (cid) {
                    if (!placed[cid] && memberMap[cid]) {
                        placed[cid] = true;
                        nextGen.push(cid);
                        // Pull spouse into the same generation.
                        memberMap[cid].spouse_ids.forEach(function (sid) {
                            if (!placed[sid] && memberMap[sid]) {
                                placed[sid] = true;
                                nextGen.push(sid);
                            }
                        });
                    }
                });
            });
            currentGen = nextGen;
        }

        // Any member not yet placed goes into a final "unlinked" generation.
        var unlinked = memberIds.filter(function (id) { return !placed[id]; });
        if (unlinked.length) { generations.push(unlinked); }

        // ── DOM construction ─────────────────────────────────────────────────
        var treeEl = el('div', { className: 'dt-family-tree' });

        generations.forEach(function (genIds) {
            var genEl    = el('div', { className: 'dt-family-generation' });
            var rendered = {};

            genIds.forEach(function (id) {
                if (rendered[id]) { return; }
                rendered[id] = true;

                var m = memberMap[id];

                // Pick the first in-group spouse (if any).
                var spouseId = m.spouse_ids.find(function (sid) {
                    return memberMap[sid] && !rendered[sid];
                });
                if (spouseId) { rendered[spouseId] = true; }

                // Children shared by this person (and optionally their spouse) that
                // appear in the *next* generation.
                var sharedChildIds = m.children_ids.filter(function (cid) {
                    return memberMap[cid] && !rendered[cid];
                });
                if (spouseId) {
                    memberMap[spouseId].children_ids.forEach(function (cid) {
                        if (memberMap[cid] && !rendered[cid] && sharedChildIds.indexOf(cid) === -1) {
                            sharedChildIds.push(cid);
                        }
                    });
                }

                // Only inline children that are already present in the next generation
                // AND that are not also rendered at the top of their own generation
                // (to avoid double-rendering). For simplicity we skip inlining children
                // here and let them appear in their own generation row — the CSS border
                // provides the visual connection.
                genEl.appendChild(buildUnit(id, spouseId || null, [], memberMap));
            });

            treeEl.appendChild(genEl);
        });

        container.innerHTML = '';
        container.appendChild(treeEl);
    }

    // ── fetch & bootstrap ──────────────────────────────────────────────────────

    function fetchTree() {
        return fetch(
            dtFamilyGroups.rest_url + 'dt-family-groups/v1/family-tree/' + dtFamilyGroups.post_id,
            { headers: { 'X-WP-Nonce': dtFamilyGroups.nonce } }
        ).then(function (r) { return r.json(); });
    }

    msg(dtFamilyGroups.i18n.loading);

    fetchTree()
        .then(function (data) {
            if (data && data.code) {
                // WP REST error object.
                msg(dtFamilyGroups.i18n.error);
                return;
            }
            render(data);
        })
        .catch(function () {
            msg(dtFamilyGroups.i18n.error);
        });
})();
