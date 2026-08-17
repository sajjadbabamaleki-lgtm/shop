#!/usr/bin/env node
/**
 * Iconify, offline — browse 236 icon sets, search them, pull an SVG, and print
 * a numbered sheet to choose from.
 *
 * **Why this file exists rather than the API.** Everything Iconify serves is
 * behind <https://api.iconify.design>, and this container's egress proxy
 * answers 403 to it (`connect_rejected` — the same policy that blocks
 * iconify.design itself). Every icon CLI and MCP server built on Iconify —
 * `better-icons`, `pickapicon`, `iconify-mcp-server` — is that one host with a
 * command line on top, so all of them fail here with `Error: Forbidden`. That
 * was measured, not assumed.
 *
 * What *is* reachable is the npm registry: it sits in the proxy's `noProxy`
 * list and answers directly. Iconify ships every set to npm as
 * `@iconify-json/<prefix>`, and the index of all of them as
 * `@iconify/collections` (120KB). So the data comes down the one pipe that is
 * open, lands in this folder, and nothing here touches the network at search
 * time.
 *
 * **It writes nothing outside this folder unless asked.** Downloads land in
 * `.claude/skills/iconify/node_modules`, which is ignored; the only files it
 * creates elsewhere are the ones named with `--out`.
 *
 *     node .claude/skills/iconify/iconify.js sets [query]
 *     node .claude/skills/iconify/iconify.js add <prefix> [prefix…]
 *     node .claude/skills/iconify/iconify.js search <query> [--in a,b] [--limit n] [--json]
 *     node .claude/skills/iconify/iconify.js get <prefix:name> [--gold|--color #hex] [--size 64] [--out f.svg]
 *     node .claude/skills/iconify/iconify.js sheet <id> [id…] --out f.png [--cols 5]
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const HERE = __dirname;

/**
 * First run in a fresh container has no node_modules — the container is thrown
 * away and this folder is committed without one. So the tool installs its own
 * dependencies rather than failing with a stack trace about a missing module.
 *
 * **The manifest is written here, not committed.** npm prunes anything it
 * installed with `--no-save` on the next install, which quietly deleted a set
 * downloaded ten seconds earlier; and a committed manifest would put icon sets
 * nobody uses into the repository. So `package.json` is generated on first run
 * and ignored by git, and every install saves into it — one session's downloads
 * survive the next install, and none of it is the shop's business.
 */
function ensure(specs) {
  const missing = specs.filter((spec) => {
    try {
      require.resolve(spec, { paths: [HERE] });

      return false;
    } catch {
      return true;
    }
  }).map(packageOf);

  if (!missing.length) return;

  const manifest = path.join(HERE, 'package.json');
  if (!fs.existsSync(manifest)) {
    fs.writeFileSync(manifest, `${JSON.stringify({
      name: 'vp-skill-iconify',
      private: true,
      description: 'Downloads for the iconify skill. Generated, ignored by git; delete it and node_modules to start over.',
    }, null, 2)}\n`);
  }

  process.stderr.write(`fetching ${missing.join(' ')} from npm…\n`);
  execFileSync('npm', ['install', '--no-audit', '--no-fund', ...missing], {
    cwd: HERE,
    stdio: ['ignore', 'ignore', 'inherit'],
  });
}

/** `@iconify-json/mdi/icons.json` → `@iconify-json/mdi`: what is resolved is a
 *  file inside a package, what npm installs is the package. */
function packageOf(spec) {
  const parts = spec.split('/');

  return parts.slice(0, spec.startsWith('@') ? 2 : 1).join('/');
}

ensure(['@iconify/collections/collections.json', '@iconify/utils']);

const COLLECTIONS = require(require.resolve('@iconify/collections/collections.json', { paths: [HERE] }));
const { getIconData, iconToSVG, iconToHTML, parseIconSet, replaceIDs } = require(require.resolve('@iconify/utils', { paths: [HERE] }));

/** The page's gold. An `<img>` does not inherit `currentColor`, so an icon
 *  written out for this site has its colour baked in — the same reasoning, and
 *  the same value, as theme/make-category-icons.js. */
const GOLD = '#A08119';

// ---------------------------------------------------------------------------
// Sets on disk
// ---------------------------------------------------------------------------

/** `@iconify-json/<prefix>` if it is unpacked here, else null. */
function setPath(prefix) {
  try {
    return require.resolve(`@iconify-json/${prefix}/icons.json`, { paths: [HERE] });
  } catch {
    return null;
  }
}

function installed(prefix) {
  return setPath(prefix) !== null;
}

/** Fetch sets from npm into this folder. They are a cache here, never a
 *  dependency of the shop: a set whose artwork actually ships belongs in
 *  theme/package.json, alongside the generator that reads it. */
function add(prefixes) {
  const wanted = prefixes.filter((p) => {
    if (!COLLECTIONS[p]) {
      throw new Error(`No Iconify set has the prefix "${p}". Try: sets ${p}`);
    }

    return !installed(p);
  });

  if (!wanted.length) return;

  ensure(wanted.map((p) => `@iconify-json/${p}/icons.json`));

  const missed = wanted.filter((p) => !installed(p));
  if (missed.length) throw new Error(`npm did not produce ${missed.map((p) => `@iconify-json/${p}`).join(', ')}`);
}

/** Every set unpacked here. */
function installedSets() {
  const dir = path.join(HERE, 'node_modules/@iconify-json');
  if (!fs.existsSync(dir)) return [];

  return fs.readdirSync(dir).filter((p) => COLLECTIONS[p] && installed(p)).sort();
}

const loaded = new Map();

function load(prefix) {
  if (!loaded.has(prefix)) {
    const file = setPath(prefix);
    if (!file) throw new Error(`@iconify-json/${prefix} is not here yet. Run: add ${prefix}`);
    loaded.set(prefix, JSON.parse(fs.readFileSync(file, 'utf8')));
  }

  return loaded.get(prefix);
}

/** `Lucide — 1768 icons, ISC`, off the index rather than the set, so it costs
 *  nothing to print for a set that is not installed. */
function describe(prefix) {
  const c = COLLECTIONS[prefix];
  if (!c) return prefix;

  return `${c.name} — ${c.total} icons, ${c.license?.title ?? 'licence unstated'}${c.palette ? ', fixed palette' : ''}`;
}

// ---------------------------------------------------------------------------
// Search
// ---------------------------------------------------------------------------

/**
 * How well `name` answers `query`; higher is better, 0 is not a match.
 *
 * Iconify's own search is a server. This is the part of it that can be done on
 * a name list: names are `arrow-right`, `shoe-2`, `bag-4-fill` — hyphen-cut
 * words — so the ranking is about *where* the query lands. The whole name beats
 * a whole word beats a fragment, and among equals the shorter name wins, which
 * is what puts `shoe` above `shoe-sneaker-fill-duotone`.
 */
function score(name, query) {
  if (name === query) return 1000;

  const words = name.split(/[-_]/);
  if (words.includes(query)) return 800 - name.length;
  if (name.startsWith(`${query}-`)) return 700 - name.length;
  if (words.some((w) => w.startsWith(query))) return 600 - name.length;
  if (name.includes(query)) return 500 - name.length;

  return 0;
}

/** Every drawable name in a set — icons and aliases both, because an alias is a
 *  perfectly good icon id and a good part of a set's vocabulary lives in them. */
function names(prefix) {
  const out = [];
  parseIconSet(load(prefix), (name) => out.push(name));

  return out;
}

function search(query, prefixes, limit) {
  const hits = [];

  for (const prefix of prefixes) {
    for (const name of names(prefix)) {
      const s = score(name, query);
      if (s > 0) hits.push({ id: `${prefix}:${name}`, score: s });
    }
  }

  hits.sort((a, b) => b.score - a.score || a.id.localeCompare(b.id));

  return hits.slice(0, limit);
}

// ---------------------------------------------------------------------------
// SVG
// ---------------------------------------------------------------------------

/**
 * `iconToSVG` is Iconify's own renderer, so an alias's rotation and flips are
 * applied the way the set's author meant them — that is why `@iconify/utils` is
 * a dependency instead of reading `icons.json` by hand. `replaceIDs` follows
 * because ids inside a body are the author's, and two icons on one page would
 * otherwise share them.
 */
function render(id, { color, size } = {}) {
  const [prefix, name] = split(id);
  const data = getIconData(load(prefix), name);
  if (!data) throw new Error(`${prefix} has no icon called "${name}". Try: search ${name} --in ${prefix}`);

  const { attributes, body } = iconToSVG(data, size ? { width: String(size), height: String(size) } : {});
  const ink = replaceIDs(body);

  return { attributes, body: color ? ink.replaceAll('currentColor', color) : ink };
}

function svg(id, options) {
  const { attributes, body } = render(id, options);

  return `${iconToHTML(body, attributes)}\n`;
}

function split(id) {
  const at = id.lastIndexOf(':');
  if (at < 1) throw new Error(`"${id}" is not an icon id — they read prefix:name, e.g. lucide:home`);

  return [id.slice(0, at), id.slice(at + 1)];
}

/**
 * A numbered contact sheet, because that is how icons get chosen here.
 *
 * Every icon in this repository was picked by the client off a sheet — «شماره ۳
 * خوبه اونکه انگار در حال دویدنه». A list of names in a terminal is not a thing
 * anyone can answer; twelve drawings with numbers under them is.
 */
async function sheet(ids, { out, color, cols, cell = 120 }) {
  const sharp = require(sharpPath());

  const pad = 14;
  const label = 22;
  const rows = Math.ceil(ids.length / cols);
  const width = cols * cell + pad * 2;
  const height = rows * (cell + label) + pad * 2;

  const cells = ids.map((id, i) => {
    const x = pad + (i % cols) * cell;
    const y = pad + Math.floor(i / cols) * (cell + label);

    // A nested <svg> with its own viewBox, not the icon's file dropped in: the
    // set's own width and height are its business (16, 24, 32 and 48 all appear
    // among these sets) and the sheet's job is to show them at one size.
    const { attributes, body } = render(id, { color });
    const art = `<svg x="${x + cell * 0.2}" y="${y + cell * 0.1}" width="${cell * 0.6}" height="${cell * 0.6}"`
      + ` viewBox="${attributes.viewBox}">${body}</svg>`;

    return art
      + `<text x="${x + cell / 2}" y="${y + cell * 0.86}" text-anchor="middle" font-family="sans-serif" font-size="15" font-weight="700" fill="#111">${i + 1}</text>`
      + `<text x="${x + cell / 2}" y="${y + cell + 4}" text-anchor="middle" font-family="sans-serif" font-size="10" fill="#666">${escape(id)}</text>`;
  });

  const page = `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">`
    + `<rect width="${width}" height="${height}" fill="#fff"/>${cells.join('')}</svg>`;

  fs.mkdirSync(path.dirname(out), { recursive: true });
  await sharp(Buffer.from(page), { density: 144 }).resize(width, height).png().toFile(out);

  return { out, width, height };
}

/** The repo's own sharp if `theme/npm install` has been run — it is 50MB and
 *  there is no reason to have two of it — and this folder's otherwise. */
function sharpPath() {
  for (const from of [HERE, path.join(HERE, '../../../theme')]) {
    try {
      return require.resolve('sharp', { paths: [from] });
    } catch {
      // The next candidate, or the download below.
    }
  }

  ensure(['sharp']);

  return require.resolve('sharp', { paths: [HERE] });
}

function escape(s) {
  return s.replace(/[<>&]/g, (c) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c]));
}

// ---------------------------------------------------------------------------
// Command line
// ---------------------------------------------------------------------------

function options(argv) {
  const opts = {};
  const rest = [];

  for (let i = 0; i < argv.length; i++) {
    if (!argv[i].startsWith('--')) {
      rest.push(argv[i]);
      continue;
    }

    const [flag, inline] = argv[i].slice(2).split(/=(.*)/);
    const takesValue = !['json', 'gold'].includes(flag);
    opts[flag] = takesValue ? (inline ?? argv[++i]) : true;
  }

  return { opts, rest };
}

const USAGE = `Iconify, offline — sets come from npm; nothing calls api.iconify.design (it is blocked here).

  sets [query]                    which of the 236 sets exist, and their licences
  add <prefix> [prefix…]          download sets from npm into the skill folder
  search <query> [--in a,b] [--limit 40] [--json]
  get <prefix:name> [--gold|--color #hex] [--size 64] [--out f.svg]
  sheet <id> [id…] --out f.png [--cols 5] [--gold]

search with no --in searches every set already downloaded; --in fetches any set
it names that is missing. Ids read prefix:name — lucide:home.`;

async function main(argv) {
  const [command] = argv;
  const { opts, rest } = options(argv.slice(1));
  const colour = opts.gold ? GOLD : opts.color;

  switch (command) {
    case 'sets': {
      const q = (rest[0] ?? '').toLowerCase();
      const found = Object.entries(COLLECTIONS)
        .filter(([prefix, c]) => !q || prefix.includes(q) || c.name.toLowerCase().includes(q)
          || (c.category ?? '').toLowerCase().includes(q)
          || (c.tags ?? []).some((t) => t.toLowerCase().includes(q))
          || (c.samples ?? []).some((s) => s.includes(q)))
        .sort(([, a], [, b]) => b.total - a.total);

      for (const [prefix, c] of found) {
        console.log(`${installed(prefix) ? '●' : '○'} ${prefix.padEnd(28)} ${describe(prefix)}`);
        if (c.samples?.length) console.log(`  ${' '.repeat(28)} e.g. ${c.samples.slice(0, 6).join(', ')}`);
      }

      console.log(`\n${found.length} of ${Object.keys(COLLECTIONS).length} sets; ● is downloaded here, ○ needs \`add\`.`);
      break;
    }

    case 'add': {
      if (!rest.length) throw new Error('add needs at least one prefix');
      add(rest);
      for (const p of rest) console.log(`● ${p.padEnd(28)} ${describe(p)}`);
      break;
    }

    case 'search': {
      const [query] = rest;
      if (!query) throw new Error('search needs something to search for');

      const asked = opts.in ? opts.in.split(',').map((s) => s.trim()).filter(Boolean) : [];
      if (asked.length) add(asked);

      const prefixes = asked.length ? asked : installedSets();
      if (!prefixes.length) throw new Error('No sets downloaded yet. Start with: add lucide');

      const hits = search(query.toLowerCase(), prefixes, Number(opts.limit ?? 40));

      if (opts.json) {
        console.log(JSON.stringify({ query, searched: prefixes, icons: hits.map(({ id }) => id) }, null, 2));
        break;
      }

      for (const hit of hits) console.log(hit.id);
      console.log(`\n${hits.length} match(es) in ${prefixes.length} set(s): ${prefixes.join(', ')}`);
      if (!hits.length) console.log('`sets <word>` finds a set worth adding, then search again with --in.');
      break;
    }

    case 'get': {
      const [id] = rest;
      if (!id) throw new Error('get needs an icon id, e.g. lucide:home');
      add([split(id)[0]]);

      const out = svg(id, { color: colour, size: opts.size && Number(opts.size) });

      if (opts.out) {
        fs.mkdirSync(path.dirname(path.resolve(opts.out)), { recursive: true });
        fs.writeFileSync(opts.out, out);
        console.log(`${opts.out} — ${describe(split(id)[0])}`);
        console.log('Shipping it? That licence travels with the copy — see SKILL.md, "An icon that ships".');
      } else {
        process.stdout.write(out);
      }
      break;
    }

    case 'sheet': {
      if (!rest.length) throw new Error('sheet needs at least one icon id');
      if (!opts.out) throw new Error('sheet needs --out, e.g. --out /tmp/sheet.png');
      add([...new Set(rest.map((id) => split(id)[0]))]);

      const made = await sheet(rest, {
        out: path.resolve(opts.out),
        color: colour ?? GOLD,
        cols: Number(opts.cols ?? 5),
      });
      console.log(`${made.out} — ${rest.length} icons, ${made.width}×${made.height}`);
      break;
    }

    default:
      console.log(USAGE);

      return command && !['help', '--help', '-h'].includes(command) ? 1 : 0;
  }

  return 0;
}

main(process.argv.slice(2))
  .then((code) => process.exit(code))
  .catch((error) => {
    console.error(error.message);
    process.exit(1);
  });
