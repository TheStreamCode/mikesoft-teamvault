## Maintainer Checklist

- [ ] The scope, user impact, and compatibility implications are described.
- [ ] Security-sensitive inputs, outputs, capabilities, nonces, SQL, and filesystem paths were reviewed where applicable.
- [ ] Regression coverage was added for fixes and edge cases.
- [ ] Version fields remain aligned when this is a release change.
- [ ] `composer validate --strict`, `composer audit --locked`, and `composer ci` pass.
- [ ] JavaScript syntax/tests and WordPress Plugin Check pass.
- [ ] Documentation, changelog, localized release summaries, and security notes are synchronized.
- [ ] WordPress.org package-only files remain separate from repository-only docs.
- [ ] The runtime archive was inspected for forbidden files and secrets.
