/**
 * Map constants: regions, state abbreviations, FIPS codes, party data, district counts.
 * All data is static — no runtime lookups needed.
 */

export const REGIONS = {
    'Northeast': {
        color: 0x6366f1, hex: '#6366f1',
        states: ['Connecticut','Delaware','Maine','Maryland','Massachusetts','New Hampshire','New Jersey','New York','Pennsylvania','Rhode Island','Vermont'],
    },
    'Southeast': {
        color: 0xf59e0b, hex: '#f59e0b',
        states: ['Alabama','Arkansas','Florida','Georgia','Kentucky','Louisiana','Mississippi','North Carolina','South Carolina','Tennessee','Virginia','West Virginia'],
    },
    'Midwest': {
        color: 0x10b981, hex: '#10b981',
        states: ['Illinois','Indiana','Iowa','Kansas','Michigan','Minnesota','Missouri','Nebraska','North Dakota','Ohio','South Dakota','Wisconsin'],
    },
    'Southwest': {
        color: 0xef4444, hex: '#ef4444',
        states: ['Arizona','Colorado','Nevada','New Mexico','Oklahoma','Texas','Utah'],
    },
    'West': {
        color: 0x06b6d4, hex: '#06b6d4',
        states: ['Alaska','California','Hawaii','Idaho','Montana','Oregon','Washington','Wyoming'],
    },
};

export const STATE_ABBR_MAP = {
    'Alabama':'AL','Alaska':'AK','Arizona':'AZ','Arkansas':'AR','California':'CA',
    'Colorado':'CO','Connecticut':'CT','Delaware':'DE','Florida':'FL','Georgia':'GA',
    'Hawaii':'HI','Idaho':'ID','Illinois':'IL','Indiana':'IN','Iowa':'IA','Kansas':'KS',
    'Kentucky':'KY','Louisiana':'LA','Maine':'ME','Maryland':'MD','Massachusetts':'MA',
    'Michigan':'MI','Minnesota':'MN','Mississippi':'MS','Missouri':'MO','Montana':'MT',
    'Nebraska':'NE','Nevada':'NV','New Hampshire':'NH','New Jersey':'NJ','New Mexico':'NM',
    'New York':'NY','North Carolina':'NC','North Dakota':'ND','Ohio':'OH','Oklahoma':'OK',
    'Oregon':'OR','Pennsylvania':'PA','Rhode Island':'RI','South Carolina':'SC',
    'South Dakota':'SD','Tennessee':'TN','Texas':'TX','Utah':'UT','Vermont':'VT',
    'Virginia':'VA','Washington':'WA','West Virginia':'WV','Wisconsin':'WI','Wyoming':'WY',
    'District of Columbia':'DC',
};

export const STATE_FIPS = {
    'Alabama':'01','Alaska':'02','Arizona':'04','Arkansas':'05','California':'06',
    'Colorado':'08','Connecticut':'09','Delaware':'10','Florida':'12','Georgia':'13',
    'Hawaii':'15','Idaho':'16','Illinois':'17','Indiana':'18','Iowa':'19','Kansas':'20',
    'Kentucky':'21','Louisiana':'22','Maine':'23','Maryland':'24','Massachusetts':'25',
    'Michigan':'26','Minnesota':'27','Mississippi':'28','Missouri':'29','Montana':'30',
    'Nebraska':'31','Nevada':'32','New Hampshire':'33','New Jersey':'34','New Mexico':'35',
    'New York':'36','North Carolina':'37','North Dakota':'38','Ohio':'39','Oklahoma':'40',
    'Oregon':'41','Pennsylvania':'42','Rhode Island':'44','South Carolina':'45',
    'South Dakota':'46','Tennessee':'47','Texas':'48','Utah':'49','Vermont':'50',
    'Virginia':'51','Washington':'53','West Virginia':'54','Wisconsin':'55','Wyoming':'56',
    'District of Columbia':'11',
};

export const FIPS_TO_ABBR = (() => {
    const map = {};
    for (const [name, fips] of Object.entries(STATE_FIPS)) {
        map[fips] = STATE_ABBR_MAP[name] || name;
    }
    return map;
})();

export const DISTRICT_COUNTS = {
    'Alabama':7,'Alaska':1,'Arizona':9,'Arkansas':4,'California':52,
    'Colorado':8,'Connecticut':5,'Delaware':1,'Florida':28,'Georgia':14,
    'Hawaii':2,'Idaho':2,'Illinois':17,'Indiana':9,'Iowa':4,'Kansas':4,
    'Kentucky':6,'Louisiana':6,'Maine':2,'Maryland':8,'Massachusetts':9,
    'Michigan':13,'Minnesota':8,'Mississippi':4,'Missouri':8,'Montana':2,
    'Nebraska':3,'Nevada':4,'New Hampshire':2,'New Jersey':12,'New Mexico':3,
    'New York':26,'North Carolina':14,'North Dakota':1,'Ohio':15,'Oklahoma':5,
    'Oregon':6,'Pennsylvania':17,'Rhode Island':2,'South Carolina':7,
    'South Dakota':1,'Tennessee':9,'Texas':38,'Utah':4,'Vermont':1,
    'Virginia':11,'Washington':10,'West Virginia':2,'Wisconsin':8,'Wyoming':1,
};

/* Reverse lookup: state name → region name */
export const stateToRegion = {};
for (const [regionName, region] of Object.entries(REGIONS)) {
    for (const stateName of region.states) {
        stateToRegion[stateName] = regionName;
    }
}

/* Party color mappings */
const _GOP  = '#ef4444';   // Republican red
const _IND  = '#94a3b8';   // Independent grey

// U (Unknown) is lighter than Independent's #94a3b8 — both read as neutral
// gray, but #64748b previously used here fails WCAG AA (~3.7:1) as text
// against the map's dark panel backgrounds; #a7b4c7 clears 4.5:1 comfortably
// while staying visually distinct from I's shade.
export const PARTY_HEX = {
    R: '#ef4444', D: '#3b82f6', I: '#94a3b8', L: '#eab308', G: '#22c55e', U: '#a7b4c7',
};

export const PARTY_INT = {
    R: 0xef4444, D: 0x3b82f6, I: 0x94a3b8, L: 0xeab308, G: 0x22c55e, U: 0xa7b4c7,
};

export const PARTY_LABEL = {
    R: 'Republican', D: 'Democratic', I: 'Independent', L: 'Libertarian', G: 'Green', U: 'Unknown',
};

/**
 * Congressional district party control map.
 * Key format: "STATEABBR-DISTNUM" (e.g., "CA-33", "AK-AL").
 * Populated at runtime from governor-parties API and DISTRICT_CONFIG.
 */
export const DISTRICT_PARTY_MAP = {};

export const OFFICE_ROLES = {
    'U.S. Senators':      'Represents the entire state in the U.S. Senate for a 6-year term. Each state elects two, who vote on federal legislation, confirm presidential nominees, and ratify treaties.',
    'Governor':           'Chief executive of the state. Signs or vetoes legislation, commands the National Guard, and oversees all state agencies.',
    'Lieutenant Governor':'Second-in-command; presides over the state senate in many states and assumes the governorship if needed.',
    'Attorney General':   "State's chief law-enforcement officer — represents the state in litigation and leads consumer-protection efforts.",
    'State Treasurer':    'Manages the state\'s financial assets, public fund investments, and debt.',
    'State Controller':   'Audits state spending and oversees public-fund accounting.',
    'Secretary of State': 'Manages elections, certifies results, and maintains official state records.',
};

export const CITY_OFFICE_ROLES = {
    'Mayor':                    'Chief executive of the city. Proposes the municipal budget, oversees city departments, and sets local policy priorities.',
    'City Council':             'Legislative body of the city. Passes local ordinances, approves the budget, and represents neighborhood districts.',
    'City Manager':             'Professional administrator hired by the council to run day-to-day city operations.',
    'City Attorney':            'Legal counsel for the city; advises departments and represents the city in litigation.',
    'City Treasurer':           'Manages city funds, investments, and financial reporting.',
    'City Clerk':               'Maintains official city records, administers elections, and manages public notices.',
    'District Attorney':        'Elected prosecutor who decides which criminal cases to pursue in the county.',
    'County Executive':         'Chief executive of the county government, similar to a governor at the county level.',
    'County Sheriff':           'Elected law-enforcement officer responsible for county-wide policing and jail operations.',
    'School Board Member':      'Elected official governing the local public school district — sets policy, hires superintendent, approves budget.',
    'Superior Court Judge':     'Elected judge presiding over civil and criminal cases in the county trial court. Runs county-wide (often for a specific numbered seat), not by congressional district.',
};