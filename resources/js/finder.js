/**
 * Pure functions for the suburb and postcode finder. The whole list of
 * localities is downloaded once and searched in the browser, so nothing a
 * visitor types is sent to the server.
 */

export const MAX_MATCHES = 8;

/**
 * Lower case, without accents or punctuation, so "Mt. Évelyn" matches "mt evelyn".
 */
export function normalise(text) {
    return (text || '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

/**
 * Adds the search keys to the downloaded data once.
 */
export function prepareLocalities(data) {
    return {
        districts: data.districts,
        localities: data.localities.map((locality) => ({ ...locality, key: normalise(locality.name) })),
    };
}

/**
 * Localities whose name, or any word in it, starts with the query; or, for
 * a number, whose postcode starts with it. Names starting with the query
 * come first, then shorter names.
 */
export function searchLocalities(data, query, limit = MAX_MATCHES) {
    const needle = normalise(query);

    if (needle.length < 2) {
        return [];
    }

    const isPostcode = /^\d+$/.test(needle);
    const ranked = [];

    for (const locality of data.localities) {
        let rank = null;

        if (isPostcode) {
            rank = locality.postcodes.some((postcode) => postcode.startsWith(needle)) ? 0 : null;
        } else if (locality.key.startsWith(needle)) {
            rank = 0;
        } else if (locality.key.includes(` ${needle}`)) {
            rank = 1;
        }

        if (rank !== null) {
            ranked.push({ rank, locality });
        }
    }

    return ranked
        .sort((a, b) => a.rank - b.rank || a.locality.name.length - b.locality.name.length || a.locality.name.localeCompare(b.locality.name))
        .slice(0, limit)
        .map(({ locality }) => locality);
}

/**
 * The districts a locality falls in, largest share first, in words.
 */
export function describeDistricts(data, locality) {
    const many = locality.districts.length > 1;

    return [...locality.districts]
        .sort((a, b) => b.share - a.share)
        .map((district) => {
            const name = data.districts[district.slug] ?? district.slug;

            return {
                slug: district.slug,
                name,
                share: district.share,
                label: many ? `${district.share >= 0.5 ? 'Most' : 'Part'} of ${locality.name} is in ${name}` : `${locality.name} is in ${name}`,
            };
        });
}
