# .ddev/.setup/hooks/post-typo3-setup.sh: repo-owned, sourced by utils.sh after `typo3 setup`.

# `typo3 setup --create-site` adds its own page TypoScript: a sys_template record in
# TYPO3 13, a site setup.typoscript in TYPO3 14. Either one hides the TypoScript of
# the Petstore site set, which renders the plugin as page body.
mysql -h db -u root -proot "$DATABASE" -e "DELETE FROM sys_template"
rm -f "$BASE_PATH/config/sites/main/setup.typoscript" "$BASE_PATH/config/sites/main/constants.typoscript"
