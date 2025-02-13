#!/usr/bin/env bash

echo $0 $1

if [ $# -lt 1 ]; then
	echo "usage: $0 <version>"
	exit 1
fi

VERSION=$1
TMP_DIR="./tmp/package/"
PACKAGE_DIR="${TMP_DIR}${VERSION}"
WORKING_DIR=$PWD
PACKAGE_NAME="gravity-pdf"

# Create the working directory
mkdir -p ${PACKAGE_DIR}

# Get an archive of our plugin
git archive HEAD ${BRANCH} --output ${PACKAGE_DIR}/package.tar.gz
tar -zxf ${PACKAGE_DIR}/package.tar.gz --directory ${PACKAGE_DIR} && rm -f ${PACKAGE_DIR}/package.tar.gz

# Run Composer
yarn install --cwd ${PACKAGE_DIR}
yarn --cwd ${PACKAGE_DIR} build:production
composer install --no-dev  --prefer-dist --optimize-autoloader --working-dir ${PACKAGE_DIR}

PLUGIN_DIR="$PACKAGE_DIR/" bash ./bin/vendor-prefix.sh

# Cleanup Node JS
rm -f -R ${PACKAGE_DIR}/node_modules

# Cleanup additional build files
FILES=(
"${PACKAGE_DIR}/composer.json"
"${PACKAGE_DIR}/composer.lock"
"${PACKAGE_DIR}/package.json"
"${PACKAGE_DIR}/yarn.lock"
"${PACKAGE_DIR}/.babelrc"
"${PACKAGE_DIR}/webpack.config.js"
"${PACKAGE_DIR}/php-scoper.phar"
"${PACKAGE_DIR}/vendor_prefixed/.gitkeep"
"${PACKAGE_DIR}/.nvmrc"
"${PACKAGE_DIR}/.wp-env.json"
"${PACKAGE_DIR}/.testcaferc.js"
)

for i in "${FILES[@]}"
do
    rm -f ${i}
done

rm -f -R "${PACKAGE_DIR}/src/assets/scss"
rm -f -R "${PACKAGE_DIR}/src/assets/js"
rm -f -R "${PACKAGE_DIR}/bin"
rm -f -R "${PACKAGE_DIR}/.php-scoper"
rm -f -R "${PACKAGE_DIR}/webpack-configs"

# Generate language files
cd "${PACKAGE_DIR}"
npm install --global wp-pot-cli
wp-pot --domain gravity-pdf --src 'src/**/*.php' --src 'pdf.php' --src 'api.php' --src 'gravity-pdf-updater.php' --package 'Gravity PDF' --dest-file src/assets/languages/gravity-pdf.pot > /dev/null

# Create zip package
cd "../"

rm -r -f "${PACKAGE_NAME}"
mv ${VERSION} "${PACKAGE_NAME}"
zip -r -q "${PACKAGE_NAME}-${VERSION}.zip" "${PACKAGE_NAME}"
mv "${PACKAGE_NAME}" ${VERSION}
