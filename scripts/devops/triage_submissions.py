#!/usr/bin/env python3
"""Split exported webform submissions into "likely spam" and "worth a look".

Usage:
  scripts/devops/triage_submissions.py <folder-with-csvs> [--threshold N]

Reads every *.csv in the folder (as produced by `drush webform:export ...
--exporter=delimited --delimiter=, --header-format=key`) and writes, next to
each file, <name>.spam.csv and <name>.review.csv plus a summary.txt.

Each submission gets a spam score from simple, explainable rules (a web
link in the message, known spam phrases, a name that is just the email
address, the same message sent more than once, non-Latin script, and so
on). Score >= threshold (default 2) is "likely spam". The reasons are
written into the output so the split can be checked. No data leaves the
folder; nothing is uploaded anywhere.
"""
import csv, os, re, sys, glob, collections, unicodedata

THRESHOLD = 2
SPAM_PHRASES = [
    'seo', 'backlink', 'search engine', 'rank', 'traffic', 'guest post', 'sponsored post',
    'link building', 'domain authority', 'web design', 'website design', 'redesign',
    'developer', 'crypto', 'bitcoin', 'forex', 'investment', 'loan', 'casino', 'betting',
    'viagra', 'cialis', 'pharmacy', 'earn money', 'make money', 'passive income',
    'dear website owner', 'dear sir/madam', 'to whom it may concern', 'business proposal',
    'increase your sales', 'lead generation', 'marketing services', 'app development',
    'mobile app', 'logo design', 'video editing', 'ai tool', 'chatgpt', 'unsubscribe',
    'click here', 'special offer', 'limited time', 'discount', 'free trial', 'telegram',
    'whatsapp me', 'skype', 'hosting', 'wordpress', 'shopify', 'amazon', 'ebay',
    'the choir should sing about', 'i want to promote', 'my company can',
    'back-office', 'back office', 'bookkeep', 'data entry', 'web research', 'press release',
    'your website', 'your business', 'your company', 'your brand', 'social media', 'subscriber',
    'send us your', 'pricing', 'product catalog', 'cooperation with you', 'business relationship',
    'leads', 'online presence', 'posture', 'replica', 'quickbooks', 'administrator', 'did you know',
    'growth service', 'free report', 'noticed a few issues', 'cybersecurity', 'recurring payment',
    'internationalgospelchoir.uk admin', 'hello internationalgospelchoir.uk', 'dear internationalgospelchoir.uk',
    'companies like yours', 'company like yours', 'share your offer',
]
TEST_DOMAIN_RE = re.compile(r'@(mail\.ru|yandex|qq\.com|163\.com|rambler|formtest|dont-reply|testing-your-form|example\.com|[a-z0-9-]+\.ru$)', re.I)
RANDOM_NAME_RE = re.compile(r'^[A-Za-z]{6,}$')

def looks_random(name):
    # e.g. "rDnsDkAL", "kuMpvIErrSOnmtL": several case flips inside a word,
    # not counting the capital that starts each word ("Jane Smith" is fine).
    flips = 0
    for word in name.split():
        if not RANDOM_NAME_RE.match(word):
            continue
        body = word[1:]
        flips += sum(1 for a, b in zip(body, body[1:]) if a.islower() != b.islower())
    return flips >= 3

def odd_case(word):
    # An uppercase letter after the first character: "gOVHw", "rQZMBjm".
    return any(ch.isupper() for ch in word[1:]) and not word.isupper()

def gibberish_ratio(text):
    words = re.findall(r'[A-Za-z]{3,}', text)
    if len(words) < 3:
        return 0.0
    return sum(1 for w in words if odd_case(w)) / len(words)

LINK_RE = re.compile(r'(https?://|www\.|\.(com|net|org|io|ru|xyz|top|info|biz|co)\b)', re.I)
TEXT_FIELDS = ['message', 'subject', 'your_message', 'your_name', 'name', 'your_email', 'email',
               'your_phone', 'call_back_phone_number', 'date_and_time_provisional']

def norm(s):
    return re.sub(r'\s+', ' ', (s or '')).strip()

def script_of(s):
    non_latin = 0; letters = 0
    for ch in s:
        if ch.isalpha():
            letters += 1
            name = unicodedata.name(ch, '')
            if not name.startswith('LATIN'):
                non_latin += 1
    return (non_latin / letters) if letters else 0.0

def score_row(row, dup_counts):
    reasons = []
    msg = norm(row.get('message') or row.get('your_message') or '')
    subj = norm(row.get('subject') or '')
    name = norm(row.get('name') or row.get('your_name') or '')
    email = norm(row.get('email') or row.get('your_email') or '').lower()
    text = ' '.join([subj, msg]).lower()
    if LINK_RE.search(msg) or LINK_RE.search(subj):
        reasons.append('link'); reasons.append('link')  # counts double: a link in an enquiry to a choir is nearly always spam
    hits = [p for p in SPAM_PHRASES if p in text]
    if hits:
        reasons.append('phrases:' + ','.join(hits[:4]))
        if len(hits) >= 2:
            reasons.append('phrases-x%d' % len(hits))  # two or more sales phrases: decisive
    if email and name and (name.lower() == email.split('@')[0] or name.lower() == email):
        reasons.append('name=email')
    if name and re.search(r'\d', name):
        reasons.append('digits-in-name')
    if name and re.fullmatch(r'[A-Za-z]{1,40}', name) and not re.search(r'[aeiouy]', name, re.I):
        reasons.append('no-vowels-name')
    if len(msg) < 15:
        reasons.append('very-short')
    if name and looks_random(name):
        reasons.append('random-name')
    if gibberish_ratio(msg) >= 0.4:
        reasons.append('gibberish')
    if not msg and name and ' ' not in name:
        reasons.append('empty+single-word-name')
    if len(msg) > 2500:
        reasons.append('very-long')
    if script_of(msg) > 0.3:
        reasons.append('non-latin')
    if dup_counts.get(msg, 0) > 1 and len(msg) > 30:
        reasons.append('duplicate-x%d' % dup_counts[msg])
        if dup_counts[msg] >= 3:
            reasons.append('duplicate-x%d' % dup_counts[msg])  # three or more identical messages: decisive
    if email and TEST_DOMAIN_RE.search(email):
        reasons.append('email-domain')
    # Signals for a real enquiry lower the score.
    if re.search(r'\b(choir|sing|singing|rehears|concert|book|booking|wedding|perform|gospel|join|audition|event|venue|ticket)\b', text):
        reasons.append('choir-words(-1)')
    score = sum(1 for r in reasons if not r.endswith('(-1)')) - sum(1 for r in reasons if r.endswith('(-1)'))
    # A link plus spam phrases is decisive even with choir words.
    if 'link' in reasons and any(r.startswith('phrases') for r in reasons):
        score = max(score, THRESHOLD)
    return score, reasons

def main():
    global THRESHOLD
    args = sys.argv[1:]
    if not args:
        print(__doc__); sys.exit(1)
    folder = args[0]
    if '--threshold' in args:
        THRESHOLD = int(args[args.index('--threshold') + 1])
    summary = []
    for path in sorted(glob.glob(os.path.join(folder, '*.csv'))):
        base = os.path.basename(path)
        if base.endswith('.spam.csv') or base.endswith('.review.csv'):
            continue
        with open(path, newline='', encoding='utf-8', errors='replace') as fh:
            rows = list(csv.DictReader(fh))
        dup_counts = collections.Counter(norm(r.get('message') or r.get('your_message') or '') for r in rows)
        out_cols = ['created', 'score', 'reasons'] + [c for c in TEXT_FIELDS if rows and c in rows[0]]
        spam, review = [], []
        for r in rows:
            s, reasons = score_row(r, dup_counts)
            o = {c: r.get(c, '') for c in out_cols}
            o['score'] = s; o['reasons'] = ' '.join(reasons)
            (spam if s >= THRESHOLD else review).append(o)
        for suffix, data in (('.spam.csv', spam), ('.review.csv', review)):
            with open(path[:-4] + suffix, 'w', newline='', encoding='utf-8') as fh:
                w = csv.DictWriter(fh, fieldnames=out_cols); w.writeheader(); w.writerows(data)
        summary.append('%-14s total %4d   likely spam %4d   worth a look %4d' % (base, len(rows), len(spam), len(review)))
    text = '\n'.join(summary)
    with open(os.path.join(folder, 'summary.txt'), 'w') as fh:
        fh.write(text + '\n')
    print(text)

if __name__ == '__main__':
    main()
