# .ddev/.setup/project.sh: repo-owned customizations for `ddev install`.
# Sourced by utils.sh on every install; not managed by the add-on.

# The Petstore fixture extension replaces the default test/sitepackage. Its site
# set (see Tests/Acceptance/Fixtures/sites/main) renders the Petstore plugin.
SITEPACKAGE_PACKAGES=(
    'konradmichalik/outbound-petstore:*@dev'
)

# Symlink the fixture extensions in Tests/Functional/Fixtures/Extensions into packages/.
FIXTURE_EXTENSION_DIRS=(
    'Tests/Functional/Fixtures/Extensions'
)
