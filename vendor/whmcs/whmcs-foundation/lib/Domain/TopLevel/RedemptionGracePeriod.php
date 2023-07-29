<?php

namespace WHMCS\Domain\TopLevel;

class RedemptionGracePeriod
{
    private $redemptionGracePeriods = [["at", "au", "com.au", "org.au", "net.au", "coop", "de", "es", "com.es", "nom.es", "name", "me.uk", "co.uk", "ltd.uk", "org.uk", "uk", "plc.uk", "idv.tw", "com.tw", "tw", "org.tw", "cm", "nu", "jp", "tk"], ["pe", "com.pe", "nom.pe", "net.pe", "org.pe", "tm"], "5" => ["in.net"], "15" => ["cn", "com.cn", "nm.cn", "sd.cn", "sc.cn", "sn.cn", "sh.cn", "qh.cn", "nx.cn", "co", "nom.co", "ah.cn", "bj.cn", "cq.cn", "fj.cn", "gd.cn", "gs.cn", "gx.cn", "gz.cn", "ha.cn", "hb.cn", "he.cn", "hi.cn", "hk.cn", "hl.cn", "hn.cn", "js.cn", "jx.cn", "ln.cn", "mo.cn", "net.cn", "org.cn", "sx.cn", "tj.cn", "tw.cn", "xj.cn", "xz.cn", "com.co", "net.co", "hn"], "20" => ["hn"], "21" => ["ms"], "28" => ["am", "gs"], "30" => ["asia", "biz", "bz", "ca", "cc", "nu.ca", "nf.ca", "nt.ca", "ns.ca", "nl.ca", "sk.ca", "qc.ca", "pe.ca", "on.ca", "ab.ca", "cc", "bc.ca", "mb.ca", "nb.ca", "yk.ca", "co.de", "com.de", "com", "ar.com", "br.com", "sa.com", "se.com", "cn.com", "no.com", "qc.com", "ru.com", "in", "firm.in", "co.in", "info", "jobs", "me", "mn", "mobi", "mx", "net", "se.net", "org", "us.org", "pro", "cpa.pro", "jur.pro", "law.pro", "recht.pro", "eng.pro", "aaa.pro", "med.pro", "aca.pro", "acct.pro", "bar.pro", "avocat.pro", "pw", "sc", "net.sc", "shabaka", "sx", "tel", "tv", "us", "vc", "ws", "xxx", "berlin", "quebec", "com.sg", "fr", "ac", "sg", "com.pl", "net.pl", "pl", "io", "ag", "sh", "ae.org", "info.pl", "org.pl", "com.mx", "co.com", "de.com", "eu.com", "gb.com", "gr.com", "hu.com", "jpn.com", "kr.com", "uk.com", "us.com", "uy.com", "web.com", "za.com", "gen.in", "ind.in", "net.in", "org.in", "gb.net", "se.net", "uk.net", "com.sc", "org.sc", "com.ag", "net.ag", "org.ag", "vg", "it", "la", "tc", "kids.us", "abogado", "abudhabi", "academy", "accountant", "accountants", "actor", "adult", "africa", "agency", "airforce", "alsace", "amsterdam", "apartments", "archi", "army", "art", "associates", "attorney", "auction", "auto", "autos", "baby", "band", "bank", "bar", "barcelona", "bargains", "basketball", "bayern", "beer", "best", "bet", "bible", "bid", "bike", "bingo", "bio", "black", "blackfriday", "blog", "blue", "boats", "boston", "boutique", "broker", "brussels", "build", "builders", "business", "buzz", "bzh", "cab", "cafe", "cam", "camera", "camp", "capetown", "capital", "car", "cards", "care", "career", "careers", "cars", "casa", "cash", "casino", "catalonia", "catering", "catholic", "center", "ceo", "cfd", "chat", "cheap", "christmas", "church", "city", "claims", "cleaning", "click", "clinic", "clothing", "cloud", "club", "coach", "codes", "coffee", "college", "cologne", "community", "company", "computer", "condos", "construction", "consulting", "contractors", "cooking", "cool", "corsica", "country", "coupons", "courses", "credit", "creditcard", "creditunion", "cricket", "cruises", "cymru", "dance", "date", "dating", "deals", "degree", "delivery", "democrat", "dental", "dentist", "desi", "design", "diamonds", "diet", "digital", "direct", "directory", "discount", "doctor", "dog", "domains", "dotafrica", "download", "durban", "earth", "eco", "education", "email", "energy", "engineer", "engineering", "enterprises", "equipment", "estate", "eus", "events", "exchange", "expert", "exposed", "express", "fail", "faith", "family", "fans", "farm", "fashion", "feedback", "film", "finance", "financial", "fish", "fishing", "fit", "fitness", "flights", "florist", "flowers", "football", "forsale", "foundation", "frl", "fun", "fund", "furniture", "futbol", "fyi", "gal", "gallery", "game", "games", "garden", "gay", "gea", "gent", "gift", "gifts", "gives", "giving", "glass", "glean", "global", "gmbh", "gold", "golf", "goo", "gop", "graphics", "gratis", "gree", "green", "gripe", "grocery", "group", "guide", "guitars", "guru", "hair", "halal", "hamburg", "haus", "health", "healthcare", "heart", "help", "helsinki", "here", "hiphop", "hiv", "hockey", "holdings", "holiday", "home", "homes", "horse", "hospital", "host", "hosting", "hot", "hotel", "hotels", "house", "how", "icu", "idn", "ieee", "ikano", "immo", "immobilien", "inc", "indians", "industries", "ing", "ink", "institute", "insurance", "insure", "international", "investments", "ira", "irish", "islam", "ismaili", "ist", "istanbul", "jetzt", "jewelry", "joburg", "juegos", "justforu", "kaufen", "kid", "kids", "kim", "kitchen", "kiwi", "koeln", "kosher", "kyoto", "lamborghini", "land", "lat", "latino", "law", "lawyer", "lds", "lease", "leclerc", "legal", "lgbt", "life", "lifeinsurance", "lifestyle", "lighting", "limited", "limo", "link", "live", "living", "llc", "llp", "loan", "loans", "lol", "london", "lotto", "love", "ltd", "luxe", "luxury", "madrid", "mail", "maison", "management", "map", "market", "marketing", "markets", "mba", "med", "media", "medical", "meet", "melbourne", "meme", "memorial", "men", "menu", "merck", "miami", "mls", "mma", "mobile", "mobily", "moda", "moe", "mom", "money", "mormon", "mortgage", "moscow", "moto", "motorcycles", "mov", "movie", "mozaic", "msd", "music", "mutual", "mutualfunds", "nagoya", "name", "navy", "nba", "network", "new", "news", "ngo", "ninja", "now", "nowruz", "nrw", "nyc", "okinawa", "one", "ong", "onl", "online", "ooo", "organic", "origins", "osaka", "ovh", "paris", "pars", "partners", "parts", "party", "patagonia", "pay", "persiangulf", "pet", "pets", "pharmacy", "phd", "phone", "photo", "photography", "photos", "physio", "pics", "pictures", "pid", "pink", "pizza", "place", "play", "plumbing", "plus", "poker", "porn", "press", "productions", "prof", "promo", "properties", "property", "pub", "qpon", "quebec", "racing", "radio", "realestate", "realtor", "realty", "recipes", "red", "rehab", "reise", "reisen", "reit", "ren", "rent", "rentals", "repair", "report", "republican", "rest", "restaurant", "review", "reviews", "rich", "rio", "rip", "rocks", "rodeo", "roma", "rsvp", "rugby", "ruhr", "run", "ryukyu", "saarland", "safe", "safety", "sale", "salon", "sarl", "sas", "save", "scholarships", "school", "schule", "science", "scot", "search", "secure", "security", "seek", "services", "sex", "sexy", "shia", "shiksha", "shoes", "shop", "shopping", "shopyourway", "show", "singles", "site", "ski", "skin", "soccer", "social", "software", "solar", "solutions", "soy", "spa", "space", "sport", "sports", "spot", "spreadbetting", "srl", "stada", "stockholm", "storage", "store", "stroke", "studio", "study", "style", "sucks", "supplies", "supply", "support", "surf", "surgery", "swiss", "sydney", "systems", "taipei", "tatar", "tattoo", "tax", "taxi", "team", "tech", "technology", "tel", "tennis", "thai", "theater", "theatre", "tickets", "tienda", "tips", "tires", "tirol", "today", "tokyo", "tools", "top", "tour", "tours", "town", "toys", "trade", "trading", "training", "translations", "trust", "tube", "university", "uno", "vacations", "vana", "vegas", "ventures", "versicherung", "vet", "viajes", "video", "villas", "vin", "vip", "vision", "vlaanderen", "vodka", "vote", "voting", "voto", "voyage", "wales", "wang", "watch", "watches", "weather", "web", "webcam", "webs", "website", "wed", "wedding", "weibo", "whoswho", "wien", "wiki", "win", "wine", "winners", "work", "works", "world", "wow", "wtf", "xin", "xn--11b4c3d", "xn--1ck2e1b", "xn--1qqw23a", "xn--30rr7y", "xn--3bst00m", "xn--3ds443g", "xn--3pxu8k", "xn--42c2d9a", "xn--45q11c", "xn--4gbrim", "xn--4gq48lf9j", "xn--55qw42g", "xn--55qx5d", "xn--5tzm5g", "xn--6frz82g", "xn--6qq986b3xl", "xn--6rtwn", "xn--80adxhks", "xn--80aqecdr1a", "xn--80asehdb", "xn--80aswg", "xn--8y0a063a", "xn--9et52u", "xn--9krt00a", "xn--b4w605ferd", "xn--c1avg", "xn--c1yn36f", "xn--c2br7g", "xn--cck2b3b", "xn--cckwcxetd", "xn--cg4bki", "xn--czr694b", "xn--czrs0t", "xn--czru2d", "xn--d1acj3b", "xn--dkwm73cwpn", "xn--eckvdtc9d", "xn--efvy88h", "xn--estv75g", "xn--fct429k", "xn--fes124c", "xn--fhbei", "xn--fiq228c5hs", "xn--fiq64b", "xn--fjq720a", "xn--flw351e", "xn--g2xx48c", "xn--gckr3f0f", "xn--gk3at1e", "xn--hdb9cza1b", "xn--hxt814e", "xn--i1b6b1a6a2e", "xn--imr513n", "xn--io0a7i", "xn--j1aef", "xn--jlq480n2rg", "xn--jlq61u9w7b", "xn--jvr189m", "xn--kpu716f", "xn--kput3i", "xn--mgba3a3ejt", "xn--mgbaakc7dvf", "xn--mgbab2bd", "xn--mgbb9fbpob", "xn--mgbca7dzdo", "xn--mgbi4ecexp", "xn--mgbt3dhd", "xn--mgbv6cfpo", "xn--mk1bu44c", "xn--mxtq1m", "xn--ngbc5azd", "xn--ngbe9e0a", "xn--ngbrx", "xn--nqv7f", "xn--nyqy26a", "xn--otu796d", "xn--p1acf", "xn--pbt977c", "xn--pgb3ceoj", "xn--pssy2u", "xn--q9jyb4c", "xn--qcka1pmc", "xn--rhqv96g", "xn--rovu88b", "xn--ses554g", "xn--t60b56a", "xn--tckwe", "xn--tiq49xqyj", "xn--unup4y", "xn--vhquv", "xn--vuq861b", "xn--w4rs40l", "xn--xhq521b", "xn--zfr164b", "xyz", "yachts", "yoga", "yokohama", "you", "zip", "zone", "zuerich", "zulu", "cruise", "bbb", "banque", "free", "deal", "beauty", "beknown", "cpa", "coupon", "corp", "cookingchannel", "contact", "comsec", "arab", "dds", "day", "data", "dad", "baseball", "cyou", "bauhaus", "compare", "bond", "buy", "chk", "chesapeake", "charity", "bway", "cityeats", "boo", "book", "booking", "box", "broadway", "brother", "budapest", "bugatti", "edeka", "analytics", "food", "and", "ecom", "app", "aquitaine", "foo", "fan", "est", "fly", "final", "epost", "diy", "docs", "audible", "audi", "esq", "doha", "financialaid", "dot", "dubai", "eat", "are", "notes:", "dvr", "architect", "ads", "aco", "active", "adac", "audio", "forum"], "35" => ["fm"], "40" => ["be", "eu", "nl", "li"], "90" => ["br", "ch", "co.nz", "net.nz", "org.nz", "com.br", "net.br"]];

    public static function getForTld($tld)
    {
        $gp = new self();
        $gp->redemptionGracePeriods = collect($gp->redemptionGracePeriods);
        if (substr($tld, 0, 1) == ".") {
            $tld = substr($tld, 1);
        }
        $redemptionGracePeriod = $gp->redemptionGracePeriods->filter(function ($value) use($tld) {
            return in_array($tld, $value);
        })->keys()->first();
        if (is_null($redemptionGracePeriod)) {
            $redemptionGracePeriod = 0;
        }
        return $redemptionGracePeriod;
    }
}
