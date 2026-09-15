#!/usr/bin/env python3
"""Delete old mail from a Zoho mailbox over IMAP (superadmin@ retention).

Usage:
  zoho-mailbox-purge.py --list-folders
  zoho-mailbox-purge.py [--days 30] [--folders Sent,INBOX,Trash] [--yes] [--log FILE]

Without --yes it only reports what it would delete. Credentials come from
an env file (default ~/.config/ligc/zoho-imap.env, mode 600) with lines:

  ZOHO_IMAP_HOST=imappro.zoho.com
  ZOHO_IMAP_USER=a@example.com
  ZOHO_IMAP_PASSWORD=<app-specific password from Zoho, Security, App Passwords>

Set ZOHO_IMAP_ENV to use a different file. The account needs IMAP access
enabled in Zoho Mail (Settings, Mail Accounts, IMAP access).
Scheduled nightly by tools/infrastructure/provision-site/toolkit/install_cron.sh.
See docs/dev/webform-submissions-plan.md.
"""
import argparse, datetime, imaplib, os, sys

def load_env(path):
    env = {}
    try:
        with open(path) as fh:
            for line in fh:
                line = line.strip()
                if line and not line.startswith('#') and '=' in line:
                    k, v = line.split('=', 1)
                    env[k.strip()] = v.strip().strip('"').strip("'")
    except OSError as e:
        sys.exit('Cannot read env file %s: %s' % (path, e))
    for k in ('ZOHO_IMAP_USER', 'ZOHO_IMAP_PASSWORD'):
        if not env.get(k):
            sys.exit('%s missing in %s' % (k, path))
    env.setdefault('ZOHO_IMAP_HOST', 'imappro.zoho.com')
    return env

def log(args, text):
    print(text)
    if args.log:
        with open(args.log, 'a') as fh:
            fh.write('%s %s\n' % (datetime.datetime.now().strftime('%Y-%m-%d %H:%M'), text))

def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--days', type=int, default=30, help='delete mail older than this many days (default 30)')
    ap.add_argument('--folders', default='Sent,INBOX,Trash', help='comma-separated IMAP folders (default Sent,INBOX,Trash)')
    ap.add_argument('--yes', action='store_true', help='actually delete; otherwise report only')
    ap.add_argument('--list-folders', action='store_true', help='print the mailbox folders and exit (checks credentials)')
    ap.add_argument('--log', help='append a one-line summary to this file')
    args = ap.parse_args()

    env = load_env(os.environ.get('ZOHO_IMAP_ENV', os.path.expanduser('~/.config/ligc/zoho-imap.env')))
    try:
        imap = imaplib.IMAP4_SSL(env['ZOHO_IMAP_HOST'], 993)
        imap.login(env['ZOHO_IMAP_USER'], env['ZOHO_IMAP_PASSWORD'])
    except (imaplib.IMAP4.error, OSError) as e:
        log(args, 'FAILED to connect or log in: %s' % e)
        sys.exit(2)

    if args.list_folders:
        status, boxes = imap.list()
        for b in boxes or []:
            print(b.decode() if isinstance(b, bytes) else b)
        imap.logout()
        return

    cutoff = (datetime.date.today() - datetime.timedelta(days=args.days)).strftime('%d-%b-%Y')
    summary = []
    failed = False
    for folder in [f.strip() for f in args.folders.split(',') if f.strip()]:
        status, _ = imap.select('"%s"' % folder, readonly=not args.yes)
        if status != 'OK':
            summary.append('%s: cannot open' % folder); failed = True
            continue
        status, data = imap.search(None, 'BEFORE', cutoff)
        ids = data[0].split() if status == 'OK' and data and data[0] else []
        if args.yes and ids:
            # Flag in batches; a single huge sequence set can be refused.
            for i in range(0, len(ids), 500):
                chunk = b','.join(ids[i:i + 500]).decode()
                imap.store(chunk, '+FLAGS', '\\Deleted')
            imap.expunge()
            summary.append('%s: deleted %d' % (folder, len(ids)))
        else:
            summary.append('%s: %d older than %s%s' % (folder, len(ids), cutoff, '' if args.yes else ' (dry run)'))
        imap.close()
    imap.logout()
    log(args, ('OK ' if not failed else 'PARTIAL ') + '; '.join(summary))
    sys.exit(1 if failed else 0)

if __name__ == '__main__':
    main()
