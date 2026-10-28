# Release Notes

## 4.2.0 (2026-10-28)

### What's new
- Add `verbose` option [#190](https://github.com/statamic/ssg/issues/190) by @efc
- Ability to define multiple after callbacks [#207](https://github.com/statamic/ssg/issues/207) by @efc

### What's fixed
- Fix race-condition with multiple workers [#206](https://github.com/statamic/ssg/issues/206) by @obelloc
- Fix translations for sites with an explicit lang [#221](https://github.com/statamic/ssg/issues/221) by @mynetx
- Fix `glide:data_url` producing blank output during generation [#220](https://github.com/statamic/ssg/issues/220) by @mynetx
- Automatically handle Vite hot file during static site generation [#213](https://github.com/statamic/ssg/issues/213) by @JoshSalway
- Improve output of `ssg:generate` command [#209](https://github.com/statamic/ssg/issues/209) by @duncanmcclean
- Fix generation failing with Redis when using multiple workers [#228](https://github.com/statamic/ssg/issues/228) by @duncanmcclean
- Bump `statamic/cms` to `^6.11` [#229](https://github.com/statamic/ssg/issues/229) by @duncanmcclean



## 4.1.0 (2026-03-09)

### What's new
- Support Laravel 13 [#211](https://github.com/statamic/ssg/issues/211) by @duncanmcclean

### What's fixed
- Migrate PHPUnit metadata to attributes [#212](https://github.com/statamic/ssg/issues/212) by @duncanmcclean



## 4.0.0 (2026-01-28)

### What's new
- Allow enforcement of trailing slashes on URLs and links [#203](https://github.com/statamic/ssg/issues/203) by @jesseleite

### What's fixed
- Dropped support for Laravel 11 & PHP 8.2
- Improved timezone handling [#197](https://github.com/statamic/ssg/issues/197) by @duncanmcclean
- Remove `toStringFormat` handling for Carbon 2 [#201](https://github.com/statamic/ssg/issues/201) by @duncanmcclean
