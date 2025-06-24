import fs from 'fs';
import path from 'path';
import glob from 'fast-glob';
import crypto from 'crypto';

export default ({ views }) => {

    const scriptsModuleId = 'omni/scripts';
    const stylesModuleId = 'omni/styles';
    const scriptModuleIdPrefix = scriptsModuleId + ':';

    const scriptRegex = /<script\s+omni>(.*?)<\/script>/is;
    const styleRegex = /<style\s+omni>(.*?)<\/style>/is;
    const styleImportRegex = new RegExp(`@import\\s+['"]${stylesModuleId}['"];`, 'is');

    const cssFiles = [];

    const foundFiles = {};
    views.forEach(basePath => {
        const bladeFiles = glob.sync(path.join(basePath, '/**/*.blade.php'));
        bladeFiles.forEach(file => {
            const relPath = path.relative(basePath, file);
            foundFiles[relPath] = file;
        });
    });
    const files = Object.values(foundFiles);

    let cachedFiles = {};

    function parseFile(file) {
        const fileContent = fs.readFileSync(file, 'utf8');
        let match, script = null, style = null;
        if (match = scriptRegex.exec(fileContent)) {
            script = match[1];
        }
        if (match = styleRegex.exec(fileContent)) {
            style = match[1];
        }
        return { script, style };
    }

    function hashFile(file) {
        return crypto.createHash('md5').update(file).digest('hex');
    }

    function invalidateFile(file) {
        const hashId = hashFile(file);
        cachedFiles[hashId] = null;
    }

    function revalidateFiles() {
        files.forEach(file => {
            const hashId = hashFile(file);
            if (!cachedFiles[hashId]) {
                cachedFiles[hashId] = parseFile(file);
            }
        });
    }

    function getScripts() {
        revalidateFiles();
        return files
            .map(file => `import '${scriptModuleIdPrefix}${hashFile(file)}';`)
            .join('\n');
    }

    function getStyles() {
        revalidateFiles();
        return Object.values(cachedFiles)
            .map(item => item.style)
            .filter(Boolean)
            .join("\n");
    }

    function getScript(id) {
        const hashId = id.replace('\0' + scriptModuleIdPrefix, '');
        return cachedFiles[hashId]?.script || '';
    }
    
    return {

        name: 'omni',

        enforce: 'pre',

        handleHotUpdate({ file, server }) {
            if (!files.includes(file)) {
                return;
            }
            invalidateFile(file);
            const moduleIds = [
                scriptsModuleId,
                stylesModuleId,
                scriptModuleIdPrefix + hashFile(file),
            ];
            moduleIds.forEach(id => {
                const module = server.moduleGraph.getModuleById('\0' + id);
                if (module) {
                    server.moduleGraph.invalidateModule(module);
                }
            });
            cssFiles.forEach(file => {
                fs.utimesSync(file, new Date(), new Date());
            });
        },

        resolveId(id) {
            if (id === scriptsModuleId) return '\0' + id;
            if (id === stylesModuleId) return '\0' + id;
            if (id.startsWith(scriptModuleIdPrefix)) return '\0' + id;
            return null;
        },
      
        load(id) {
            if (id === '\0' + scriptsModuleId) return getScripts();
            if (id === '\0' + stylesModuleId) return null;
            if (id.startsWith('\0' + scriptModuleIdPrefix)) return getScript(id);
            return null;
        },

        /*
        This is a hack because I can't figure out how to get virtual CSS imports working in
        combination with Tailwind's Vite plugin (if it's even possible). If you know how to
        do this properly please let me know. ❤️
        */
        transform(src, id) {
            const [file] = id.split('?', 2);
            const extension = path.extname(file).slice(1);
            if (extension === 'css' && styleImportRegex.test(src)) {
                if (!cssFiles.includes(file)) {
                    cssFiles.push(file);
                }
                const updatedSrc = src.replace(styleImportRegex, getStyles());
                return { code: updatedSrc, map: null };
            }
            return null;
        },

    };

}
