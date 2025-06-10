import { Input } from "../atoms";

const SearchBar = () => {
    return (
        <div>
            <Input
                type="text"
                className="form-control  rounded-pill px-3"
                placeholder="Rechercher"
                id="search-input"
            ></Input>
        </div>
    );
};

export default SearchBar;
