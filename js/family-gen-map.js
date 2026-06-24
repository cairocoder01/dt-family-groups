/* global dtFamilyGroups */
(function () {
    'use strict';

    var cfg           = typeof dtFamilyGroups !== 'undefined' ? dtFamilyGroups : null;
    var tileContainer = document.getElementById('dt-family-tree-container');
    var expandBtn     = document.getElementById('dt-family-open-modal');
    var modal         = document.getElementById('dt-family-modal');
    var modalTree     = document.getElementById('dt-family-modal-tree');
    var modalClose    = document.getElementById('dt-family-modal-close');
    var modalTitle    = document.getElementById('dt-family-modal-title-text');

    if (!cfg || !tileContainer) { return; }
    var i18n = cfg.i18n || {};

    // ── DOM helpers ──────────────────────────────────────────────────────────────

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'className') { node.className = attrs[k]; }
                else if (k === 'style') { node.setAttribute('style', attrs[k]); }
                else { node.setAttribute(k, attrs[k]); }
            });
        }
        (children || []).forEach(function (c) {
            if (c === null || c === undefined) { return; }
            node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
        });
        return node;
    }

    function setMsg(container, text) {
        container.innerHTML = '';
        container.appendChild(el('p', { className: 'dt-family-tree-msg' }, [text]));
    }

    // ── Person card ───────────────────────────────────────────────────────────────
    //
    // Optional parentLabels: array of name strings to render as a small italic
    // label underneath the card (used for non-universal parent identification).

    function personCard(member, parentLabels) {
        var nameEl = member.post_url
            ? el('a', { href: member.post_url }, [member.name])
            : el('span', {}, [member.name]);
        var genderEl = member.gender
            ? el('span', { className: 'dt-person-gender' }, [member.gender])
            : null;
        var statusEl = member.marital_status
            ? el('span', {
                className: 'dt-person-status',
                style: 'background:' + (member.marital_color || '#aaa'),
            }, [member.marital_status])
            : null;
        var labelEl = (parentLabels && parentLabels.length)
            ? el('span', { className: 'dt-parent-label' }, [parentLabels.join(' & ')])
            : null;
        return el('div', { className: 'dt-family-person' }, [nameEl, genderEl, statusEl, labelEl]);
    }

    // ── Family unit element: primary ⚭ spouse1 ⚭ spouse2 … ──────────────────────

    function buildUnit(primaryId, spouseIds, memberMap) {
        var coupleChildren = [personCard(memberMap[primaryId])];
        spouseIds.forEach(function (sid) {
            coupleChildren.push(
                el('span', { className: 'dt-family-spouse-join', title: 'Married' }, ['⚭'])
            );
            coupleChildren.push(personCard(memberMap[sid]));
        });
        return el('div', { className: 'dt-family-unit' }, [
            el('div', { className: 'dt-family-couple' }, coupleChildren),
        ]);
    }

    // ── Flat tree builder ─────────────────────────────────────────────────────────
    //
    // Row 1 – parents: all adults with in-group children (plus their spouses),
    //         grouped with ⚭ where a spouse connection exists.
    // Row 2 – children: all members who only have in-group parents, each labelled
    //         with the parent(s) NOT shared by every child (so the universal parent
    //         like a single father is omitted and only the differing mother shows).

    function buildTreeDOM(data) {
        var members   = data.members || [];
        var groupType = data.group_type;

        var treeEl = el('div', { className: 'dt-family-tree' });

        if (groupType !== 'family') {
            treeEl.appendChild(el('p', { className: 'dt-family-tree-msg' }, [i18n.not_family || '']));
            return treeEl;
        }
        if (!members.length) {
            treeEl.appendChild(el('p', { className: 'dt-family-tree-msg' }, [i18n.no_members || '']));
            return treeEl;
        }

        var memberMap = {};
        members.forEach(function (m) { memberMap[m.ID] = m; });

        // Split: connected (any in-group family link) vs unconnected (no links).
        var connectedSet  = {};
        var unconnectedIds = [];
        members.forEach(function (m) {
            var any = (m.spouse_ids   && m.spouse_ids.length)   ||
                      (m.parent_ids   && m.parent_ids.length)   ||
                      (m.children_ids && m.children_ids.length);
            if (any) { connectedSet[m.ID] = true; }
            else     { unconnectedIds.push(m.ID); }
        });

        var connected = Object.keys(connectedSet).map(Number);

        // Parent-generation: has in-group children, OR is a spouse of someone who does.
        var parentGenSet = {};
        connected.forEach(function (id) {
            if ((memberMap[id].children_ids || []).some(function (c) { return connectedSet[c]; })) {
                parentGenSet[id] = true;
            }
        });
        connected.forEach(function (id) {
            if (!parentGenSet[id] &&
                (memberMap[id].spouse_ids || []).some(function (s) { return parentGenSet[s]; })) {
                parentGenSet[id] = true;
            }
        });

        var parentIds = connected
            .filter(function (id) { return  parentGenSet[id]; })
            .sort(function (a, b) { return a - b; });
        var childIds  = connected
            .filter(function (id) { return !parentGenSet[id]; })
            .sort(function (a, b) { return a - b; });

        // Common parents: those who appear in EVERY child's parent_ids.
        // These are omitted from child labels since they provide no differentiation.
        var commonParentSet = null;
        childIds.forEach(function (cid) {
            var cParents = {};
            (memberMap[cid].parent_ids || []).forEach(function (p) {
                if (connectedSet[p]) { cParents[p] = true; }
            });
            if (commonParentSet === null) {
                commonParentSet = cParents;
            } else {
                var intersect = {};
                Object.keys(commonParentSet).forEach(function (p) {
                    if (cParents[p]) { intersect[p] = true; }
                });
                commonParentSet = intersect;
            }
        });
        commonParentSet = commonParentSet || {};

        // ── Parents row ────────────────────────────────────────────────────────
        if (parentIds.length) {
            var parentsRowEl  = el('div', { className: 'dt-family-parents-row' });
            var placedParents = {};

            parentIds.forEach(function (id) {
                if (placedParents[id]) { return; }
                placedParents[id] = true;

                var spouseIds = (memberMap[id].spouse_ids || []).filter(function (s) {
                    return parentGenSet[s] && !placedParents[s];
                });
                spouseIds.forEach(function (s) { placedParents[s] = true; });

                parentsRowEl.appendChild(buildUnit(id, spouseIds, memberMap));
            });

            treeEl.appendChild(parentsRowEl);
        }

        // ── Children row ───────────────────────────────────────────────────────
        if (childIds.length) {
            var childrenRowEl = el('div', { className: 'dt-family-children-row' });

            childIds.forEach(function (cid) {
                // Label with the parents specific to this child (not universal across all).
                var labelParents = (memberMap[cid].parent_ids || []).filter(function (p) {
                    return connectedSet[p] && !commonParentSet[p];
                });
                var labelNames = labelParents.map(function (p) { return memberMap[p].name; });
                childrenRowEl.appendChild(personCard(memberMap[cid], labelNames));
            });

            treeEl.appendChild(childrenRowEl);
        }

        // ── Unconnected members ────────────────────────────────────────────────
        if (unconnectedIds.length) {
            treeEl.appendChild(
                el('div', { className: 'dt-family-unconnected' }, [
                    el('p', { className: 'dt-family-section-label' },
                        [i18n.other_members || 'Other Group Members']),
                    el('div', { className: 'dt-family-person-grid' },
                        unconnectedIds.map(function (id) { return personCard(memberMap[id]); })),
                ])
            );
        }

        return treeEl;
    }

    // ── Render into a target container ───────────────────────────────────────────

    function renderInto(target, data) {
        target.innerHTML = '';
        target.appendChild(buildTreeDOM(data));
    }

    // ── Modal ────────────────────────────────────────────────────────────────────

    var cachedData = null;

    function openModal() {
        if (!modal || !modalTree || !cachedData) { return; }
        renderInto(modalTree, cachedData);
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        if (modalClose) { modalClose.focus(); }
    }

    function closeModal() {
        if (!modal) { return; }
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    if (expandBtn)  { expandBtn.addEventListener('click',  openModal); }
    if (modalClose) { modalClose.addEventListener('click', closeModal); }
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) { closeModal(); }
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeModal(); }
    });

    if (modalTitle && cfg.group_name) {
        modalTitle.textContent = cfg.group_name + ' — ' +
            (i18n.family_tree_title || 'Family Tree');
    }

    // ── Fetch & bootstrap ─────────────────────────────────────────────────────────

    setMsg(tileContainer, i18n.loading || 'Loading…');

    fetch(
        cfg.rest_url + 'dt-family-groups/v1/family-tree/' + cfg.post_id,
        { headers: { 'X-WP-Nonce': cfg.nonce } }
    )
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (!data || data.code) {
            setMsg(tileContainer, i18n.error || 'Could not load family tree.');
            return;
        }
        cachedData = data;
        renderInto(tileContainer, data);

        if (data.group_type === 'family' && data.members && data.members.length) {
            if (expandBtn) { expandBtn.style.display = 'block'; }
        }

        setTimeout(function () {
            if (tileContainer.scrollHeight <= tileContainer.clientHeight + 2) {
                tileContainer.classList.add('dt-no-clip');
            }
        }, 0);
    })
    .catch(function () {
        setMsg(tileContainer, i18n.error || 'Could not load family tree.');
    });

})();
